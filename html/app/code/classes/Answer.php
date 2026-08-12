<?php

/**
 * Answer - a reply to a question.
 */
class Answer {

    private int $_id;
    private ?array $_row = null;

    public function __construct(int $id, ?array $row = null) {
        $this->_id = $id;
        if ($row !== null) $this->_row = $row;
    }

    public function id(): int { return $this->_id; }
    public function exists(): bool { return $this->row() !== []; }

    public function row(): array {
        if ($this->_row === null) {
            $db = new SQL(0);
            $this->_row = $db->cmdrow('SELECT * FROM answers WHERE id = "{0}" LIMIT 0,1', [$this->_id]);
        }
        return $this->_row;
    }

    public function __get(string $name) { return $this->row()[$name] ?? null; }
    public function refresh(): void { $this->_row = null; }

    public static function create(int $questionId, string $bodyMd, int $authorId): Answer {
        $bodyMd = trim($bodyMd);
        $minBody = Config::int("min_answer_length", 20);
        if (mb_strlen($bodyMd) < $minBody) {
            throw new \InvalidArgumentException(sprintf(__("The answer needs at least %d characters."), $minBody));
        }

        $question = new Question($questionId);
        if (!$question->exists() || $question->deleted_at !== null) throw new \InvalidArgumentException(__("This question no longer exists."));
        if ((int)$question->is_closed === 1) throw new \InvalidArgumentException(__("This question is closed and cannot be answered."));
        if ((int)$question->is_locked === 1) throw new \InvalidArgumentException(__("This question is locked."));

        if (!RateLimiter::check("answer:" . ($authorId ?: Firewall::ipHash()), 30, 3600)) {
            throw new \InvalidArgumentException(__("You have posted a lot of answers recently. Please wait a little."));
        }

        $db = new SQL(0);
        $id = $db->Create("answers", [
            "question_id"    => $questionId,
            "author_id"      => $authorId ?: null,
            "author_ip_hash" => Firewall::ipHash(),
            "body_md"        => $bodyMd,
            "body_html"      => Markdown::render($bodyMd),
        ]);
        $db->cmd('UPDATE questions SET answer_count = answer_count + 1 WHERE id = "{0}"', [$questionId]);
        $db->cmd('UPDATE users SET answer_count = answer_count + 1 WHERE id = "{0}"', [$authorId]);

        Revision::save("answer", $id, 1, $authorId, $bodyMd, "", "", "answered");
        Post::touch("question", $questionId, "answered", $authorId);
        Subscription::add($authorId, "question", $questionId);
        Notification::forNewAnswer($questionId, $id, $authorId);
        Badge::awardKey($authorId, "first_answer");
        Audit::log("answer.create", "answer:" . $id);

        return new Answer($id);
    }

    public function update(string $bodyMd, int $editorId, string $comment = ""): void {
        $bodyMd = trim($bodyMd);
        $minBody = Config::int("min_answer_length", 20);
        if (mb_strlen($bodyMd) < $minBody) {
            throw new \InvalidArgumentException(sprintf(__("The answer needs at least %d characters."), $minBody));
        }
        $revision = (int)$this->revision + 1;

        $db = new SQL(0);
        $db->Update("answers", [
            "body_md"   => $bodyMd,
            "body_html" => Markdown::render($bodyMd),
            "revision"  => $revision,
        ], $this->_id);

        Revision::save("answer", $this->_id, $revision, $editorId, $bodyMd, "", "", $comment);
        Post::touch("answer", $this->_id, "edited", $editorId);
        if ((int)$this->author_id !== $editorId) {
            Notification::create((int)$this->author_id, "post_edited", $editorId, "answer", $this->_id,
                sprintf(__("%s edited your answer"), (new User($editorId))->displayName()));
        }
        Badge::awardKey($editorId, "editor");
        $this->refresh();
        Audit::log("answer.update", "answer:" . $this->_id);
    }

    public function softDelete(int $userId): void {
        $db = new SQL(0);
        $db->Update("answers", ["deleted_at" => gmdate("Y-m-d H:i:s")], $this->_id);
        $db->cmd('UPDATE questions SET answer_count = GREATEST(0, answer_count - 1) WHERE id = "{0}"', [(int)$this->question_id]);
        $db->cmd('UPDATE users SET answer_count = GREATEST(0, answer_count - 1) WHERE id = "{0}"', [(int)$this->author_id]);
        if ((int)$this->is_accepted === 1) {
            $db->cmd('UPDATE questions SET accepted_answer_id = NULL WHERE accepted_answer_id = "{0}"', [$this->_id]);
        }
        Audit::log("answer.delete", "answer:" . $this->_id, [], $userId);
        $this->refresh();
    }

    public function restore(int $userId): void {
        $db = new SQL(0);
        $db->Update("answers", ["deleted_at" => null], $this->_id);
        $db->cmd('UPDATE questions SET answer_count = answer_count + 1 WHERE id = "{0}"', [(int)$this->question_id]);
        Audit::log("answer.restore", "answer:" . $this->_id, [], $userId);
        $this->refresh();
    }

    /**
     * All answers of a question, accepted one first.
     *
     * @param string $sort votes|newest|oldest
     */
    public static function forQuestion(int $questionId, string $sort = "votes", bool $includeDeleted = false): array {
        $db = new SQL(0);
        $order = match ($sort) {
            "newest" => "a.created_at DESC",
            "oldest" => "a.created_at ASC",
            default  => "a.is_accepted DESC, a.score DESC, a.created_at ASC",
        };
        $where = $includeDeleted ? "1=1" : "a.deleted_at IS NULL";
        return $db->cmdrows(
            'SELECT a.* FROM answers a WHERE a.question_id = "{0}" AND ' . $where . ' ORDER BY ' . $order,
            [$questionId]
        );
    }

    public function url(): string {
        return Question::permalink((int)$this->question_id) . "#answer-" . $this->_id;
    }

    public function toArray(bool $withBody = true): array {
        $row = $this->row();
        if ($row === []) return [];
        $out = [
            "id"          => (int)$row["id"],
            "question_id" => (int)$row["question_id"],
            "url"         => $this->url(),
            "score"       => (int)$row["score"],
            "is_accepted" => (int)$row["is_accepted"] === 1,
            "created_at"  => $row["created_at"],
            "updated_at"  => $row["updated_at"],
            "author"      => (new User((int)$row["author_id"]))->toArray(),
        ];
        if ($withBody) {
            $out["body_md"] = $row["body_md"];
            $out["body_html"] = $row["body_html"];
        }
        return $out;
    }
}
