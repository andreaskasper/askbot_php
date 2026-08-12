<?php

/**
 * Question - the centre of the site.
 */
class Question {

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
            $this->_row = $db->cmdrow('SELECT * FROM questions WHERE id = "{0}" LIMIT 0,1', [$this->_id]);
        }
        return $this->_row;
    }

    public function __get(string $name) { return $this->row()[$name] ?? null; }
    public function refresh(): void { $this->_row = null; }

    // -----------------------------------------------------------------------
    // Writing
    // -----------------------------------------------------------------------

    /**
     * @param string[] $tags plain tag names
     * @throws \InvalidArgumentException on validation errors
     */
    public static function create(string $title, string $bodyMd, array $tags, int $authorId): Question {
        $title = trim(preg_replace('/\s+/u', " ", $title) ?? "");
        $bodyMd = trim($bodyMd);

        self::validate($title, $bodyMd, $tags);
        if (!RateLimiter::check("ask:" . ($authorId ?: Firewall::ipHash()), 10, 3600)) {
            throw new \InvalidArgumentException(__("You have asked a lot of questions recently. Please wait a little."));
        }

        $db = new SQL(0);
        $names = Tag::normalizeList($tags);
        $id = $db->Create("questions", [
            "title"            => mb_substr($title, 0, 300),
            "slug"             => Slug::make($title, 120),
            "body_md"          => $bodyMd,
            "body_html"        => Markdown::render($bodyMd),
            "author_id"        => $authorId ?: null,
            "author_ip_hash"   => Firewall::ipHash(),
            "tags"             => implode(",", $names),
            "last_activity_at" => gmdate("Y-m-d H:i:s"),
            "last_activity_by" => $authorId ?: null,
            "last_activity_type" => "asked",
        ]);

        Tag::sync($id, $names);
        Revision::save("question", $id, 1, $authorId, $bodyMd, $title, implode(",", $names), "asked");
        $db->cmd('UPDATE users SET question_count = question_count + 1 WHERE id = "{0}"', [$authorId]);
        Subscription::add($authorId, "question", $id);
        Badge::checkUserBadges($authorId);
        Audit::log("question.create", "question:" . $id);

        return new Question($id);
    }

    public function update(string $title, string $bodyMd, array $tags, int $editorId, string $comment = ""): void {
        $title = trim(preg_replace('/\s+/u', " ", $title) ?? "");
        $bodyMd = trim($bodyMd);
        self::validate($title, $bodyMd, $tags);

        $names = Tag::normalizeList($tags);
        $revision = (int)$this->revision + 1;

        $db = new SQL(0);
        $db->Update("questions", [
            "title"     => mb_substr($title, 0, 300),
            "slug"      => Slug::make($title, 120),
            "body_md"   => $bodyMd,
            "body_html" => Markdown::render($bodyMd),
            "tags"      => implode(",", $names),
            "revision"  => $revision,
        ], $this->_id);

        Tag::sync($this->_id, $names);
        Revision::save("question", $this->_id, $revision, $editorId, $bodyMd, $title, implode(",", $names), $comment);
        Post::touch("question", $this->_id, "edited", $editorId);
        $this->refresh();

        if ((int)$this->author_id !== $editorId) {
            Notification::create((int)$this->author_id, "post_edited", $editorId, "question", $this->_id,
                sprintf(__("%s edited your question"), (new User($editorId))->displayName()));
        }
        Badge::awardKey($editorId, "editor");
        Audit::log("question.update", "question:" . $this->_id);
    }

    private static function validate(string $title, string $bodyMd, array $tags): void {
        $minTitle = Config::int("min_title_length", 15);
        $minBody = Config::int("min_question_length", 20);
        $minTags = Config::int("min_tags_per_question", 1);
        $maxTags = Config::int("max_tags_per_question", 5);

        if (mb_strlen($title) < $minTitle) throw new \InvalidArgumentException(sprintf(__("The title needs at least %d characters."), $minTitle));
        if (mb_strlen($title) > 300)       throw new \InvalidArgumentException(__("The title is too long."));
        if (mb_strlen($bodyMd) < $minBody) throw new \InvalidArgumentException(sprintf(__("The question needs at least %d characters."), $minBody));

        $names = Tag::normalizeList($tags);
        if (count($names) < $minTags) throw new \InvalidArgumentException(sprintf(__("Please add at least %d tag."), $minTags));
        if (count($names) > $maxTags) throw new \InvalidArgumentException(sprintf(__("Please use at most %d tags."), $maxTags));
    }

    public function softDelete(int $userId): void {
        $db = new SQL(0);
        $db->Update("questions", ["deleted_at" => gmdate("Y-m-d H:i:s")], $this->_id);
        $db->cmd('UPDATE users SET question_count = GREATEST(0, question_count - 1) WHERE id = "{0}"', [(int)$this->author_id]);
        Tag::sync($this->_id, []);
        Audit::log("question.delete", "question:" . $this->_id, [], $userId);
        $this->refresh();
    }

    public function restore(int $userId): void {
        $db = new SQL(0);
        $db->Update("questions", ["deleted_at" => null], $this->_id);
        Tag::sync($this->_id, Tag::normalizeList(explode(",", (string)$this->tags)));
        Audit::log("question.restore", "question:" . $this->_id, [], $userId);
        $this->refresh();
    }

    public function close(string $reason, int $userId, ?int $duplicateOf = null): void {
        $db = new SQL(0);
        $db->Update("questions", [
            "is_closed"       => 1,
            "closed_reason"   => mb_substr($reason, 0, 64),
            "closed_by_id"    => $userId,
            "closed_at"       => gmdate("Y-m-d H:i:s"),
            "duplicate_of_id" => $duplicateOf,
        ], $this->_id);
        Notification::create((int)$this->author_id, "question_closed", $userId, "question", $this->_id, __("Your question was closed"));
        Audit::log("question.close", "question:" . $this->_id, ["reason" => $reason], $userId);
        $this->refresh();
    }

    public function reopen(int $userId): void {
        $db = new SQL(0);
        $db->Update("questions", ["is_closed" => 0, "closed_reason" => "", "closed_by_id" => null, "closed_at" => null], $this->_id);
        Audit::log("question.reopen", "question:" . $this->_id, [], $userId);
        $this->refresh();
    }

    /** Accept an answer (or unaccept when the same one is passed again). */
    public function accept(int $answerId, int $userId): bool {
        if ((int)$this->author_id !== $userId && !MyUser::isModerator()) {
            throw new \RuntimeException(__("Only the author of the question can accept an answer."));
        }
        $db = new SQL(0);
        $answer = $db->cmdrow('SELECT * FROM answers WHERE id = "{0}" AND question_id = "{1}" LIMIT 0,1', [$answerId, $this->_id]);
        if ($answer === []) throw new \RuntimeException(__("This answer does not belong to this question."));

        $current = (int)$this->accepted_answer_id;
        $db->begin();
        try {
            if ($current > 0) {
                $db->Update("answers", ["is_accepted" => 0], $current);
                $previousAuthor = (int)$db->cmdvalue('SELECT author_id FROM answers WHERE id = "{0}"', [$current]);
                Karma::revoke($previousAuthor, "accepted_answer", "answer", $current);
                Karma::revoke($userId, "accept_answer", "answer", $current);
                $db->cmd('UPDATE users SET accepted_count = GREATEST(0, accepted_count - 1) WHERE id = "{0}"', [$previousAuthor]);
            }
            $accepting = ($current !== $answerId);
            $db->Update("questions", ["accepted_answer_id" => $accepting ? $answerId : null], $this->_id);
            if ($accepting) {
                $db->Update("answers", ["is_accepted" => 1], $answerId);
                $author = (int)$answer["author_id"];
                if ($author !== $userId) {
                    Karma::award($author, "accepted_answer", Config::int("karma_answer_accepted", 15), "answer", $answerId, $userId);
                }
                Karma::award($userId, "accept_answer", Config::int("karma_accept_answer", 2), "answer", $answerId, $userId);
                $db->cmd('UPDATE users SET accepted_count = accepted_count + 1 WHERE id = "{0}"', [$author]);
                Notification::create($author, "answer_accepted", $userId, "answer", $answerId, __("Your answer was accepted"));
                Badge::awardKey($userId, "scholar");
                Badge::checkPostBadges("answer", $answerId);
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
        Post::touch("question", $this->_id, "accepted", $userId);
        $this->refresh();
        return $accepting;
    }

    /** Count a view at most once per visitor per day. */
    public function countView(): void {
        if (Firewall::isCrawler()) return;
        $key = substr(md5(Firewall::ipHash() . "|" . ($_SERVER["HTTP_USER_AGENT"] ?? "")), 0, 32);
        try {
            $db = new SQL(0);
            $db->cmd(
                'INSERT IGNORE INTO question_views (question_id, viewer_key, view_date) VALUES ("{0}", "{1}", UTC_DATE())',
                [$this->_id, $key]
            );
            if ($db->affected() > 0) {
                $db->cmd('UPDATE questions SET view_count = view_count + 1 WHERE id = "{0}"', [$this->_id]);
            }
        } catch (\Throwable $e) {
            // A view counter is never worth an error page.
        }
    }

    // -----------------------------------------------------------------------
    // Reading
    // -----------------------------------------------------------------------

    public static function permalink(int $id, ?string $title = null): string {
        if ($title === null) {
            $db = new SQL(0);
            $title = (string)$db->cmdvalue('SELECT title FROM questions WHERE id = "{0}"', [$id]);
        }
        return url("question/" . $id . "/" . Slug::make($title, 120));
    }

    public function url(): string {
        return url("question/" . $this->_id . "/" . ($this->slug ?: Slug::make((string)$this->title, 120)));
    }

    public function tagList(): array {
        $tags = (string)$this->tags;
        return $tags === "" ? [] : explode(",", $tags);
    }

    /**
     * List questions.
     *
     * @param array $filter tag, q, scope (unanswered|accepted|bounty|favorites),
     *                      user, sort (activity|newest|votes|answers|views), page
     * @return array{items:array,total:int,pages:int,page:int}
     */
    public static function search(array $filter = []): array {
        $db = new SQL(0);
        $perPage = max(5, min(100, (int)($filter["per_page"] ?? Config::int("questions_per_page", 30))));
        $page = max(1, (int)($filter["page"] ?? 1));
        $offset = ($page - 1) * $perPage;

        $where = ["q.deleted_at IS NULL"];
        $joins = "";
        $values = [];
        $order = "q.last_activity_at DESC";

        if (!empty($filter["tag"])) {
            $joins .= ' JOIN question_tags qt ON qt.question_id = q.id JOIN tags t ON t.id = qt.tag_id';
            $where[] = 't.name = "{' . count($values) . '}"';
            $values[] = Tag::resolveSynonym(Slug::tag((string)$filter["tag"]));
        }
        if (!empty($filter["user"])) {
            $where[] = 'q.author_id = "{' . count($values) . '}"';
            $values[] = (int)$filter["user"];
        }
        if (!empty($filter["favorites_of"])) {
            $joins .= ' JOIN favorites f ON f.question_id = q.id';
            $where[] = 'f.user_id = "{' . count($values) . '}"';
            $values[] = (int)$filter["favorites_of"];
        }
        switch ((string)($filter["scope"] ?? "")) {
            case "unanswered": $where[] = 'q.answer_count = 0 AND q.is_closed = 0'; break;
            case "unsolved":   $where[] = 'q.accepted_answer_id IS NULL'; break;
            case "accepted":   $where[] = 'q.accepted_answer_id IS NOT NULL'; break;
            case "bounty":     $where[] = 'q.bounty_amount > 0 AND (q.bounty_expires_at IS NULL OR q.bounty_expires_at > UTC_TIMESTAMP())'; break;
            case "closed":     $where[] = 'q.is_closed = 1'; break;
        }
        if (!empty($filter["q"])) {
            $term = trim((string)$filter["q"]);
            $where[] = '(MATCH(q.title, q.body_md, q.tags) AGAINST ("{' . count($values) . '}" IN NATURAL LANGUAGE MODE) OR q.title LIKE "%{' . (count($values) + 1) . '}%")';
            $values[] = $term;
            $values[] = $term;
            $order = 'MATCH(q.title, q.body_md, q.tags) AGAINST ("{' . count($values) . '}" IN NATURAL LANGUAGE MODE) DESC, q.score DESC';
            $values[] = $term;
        }

        switch ((string)($filter["sort"] ?? "activity")) {
            case "newest":  $order = "q.created_at DESC"; break;
            case "votes":   $order = "q.score DESC, q.created_at DESC"; break;
            case "answers": $order = "q.answer_count DESC, q.created_at DESC"; break;
            case "views":   $order = "q.view_count DESC, q.created_at DESC"; break;
            case "hot":     $order = "(q.score * 3 + q.answer_count * 5 + q.view_count / 20) / POW(GREATEST(1, TIMESTAMPDIFF(HOUR, q.created_at, UTC_TIMESTAMP())) + 2, 1.4) DESC"; break;
            case "activity":
            default: $order = "q.last_activity_at DESC"; break;
        }

        $sqlWhere = implode(" AND ", $where);
        $total = $db->cmdint('SELECT COUNT(DISTINCT q.id) FROM questions q' . $joins . ' WHERE ' . $sqlWhere, $values);

        $rows = $db->cmdrows(
            'SELECT DISTINCT q.* FROM questions q' . $joins . ' WHERE ' . $sqlWhere .
            ' ORDER BY ' . $order . ' LIMIT ' . SQL::int($offset) . ',' . SQL::int($perPage),
            $values
        );

        return [
            "items" => $rows,
            "total" => $total,
            "page"  => $page,
            "pages" => (int)ceil($total / $perPage),
            "per_page" => $perPage,
        ];
    }

    /** Questions that look like this one - shown next to the ask form. */
    public static function similar(string $title, int $limit = 5, int $excludeId = 0): array {
        $title = trim($title);
        if (mb_strlen($title) < 6) return [];
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT id, title, slug, answer_count, score FROM questions
             WHERE deleted_at IS NULL AND id <> "{1}" AND MATCH(title, body_md, tags) AGAINST ("{0}" IN NATURAL LANGUAGE MODE)
             ORDER BY MATCH(title, body_md, tags) AGAINST ("{0}" IN NATURAL LANGUAGE MODE) DESC LIMIT ' . SQL::int($limit),
            [$title, $excludeId]
        );
    }

    public function toArray(bool $withBody = true): array {
        $row = $this->row();
        if ($row === []) return [];
        $out = [
            "id"          => (int)$row["id"],
            "title"       => $row["title"],
            "url"         => $this->url(),
            "tags"        => $this->tagList(),
            "score"       => (int)$row["score"],
            "answer_count"=> (int)$row["answer_count"],
            "view_count"  => (int)$row["view_count"],
            "is_closed"   => (int)$row["is_closed"] === 1,
            "is_answered" => $row["accepted_answer_id"] !== null,
            "accepted_answer_id" => $row["accepted_answer_id"] !== null ? (int)$row["accepted_answer_id"] : null,
            "created_at"  => $row["created_at"],
            "last_activity_at" => $row["last_activity_at"],
            "author"      => (new User((int)$row["author_id"]))->toArray(),
        ];
        if ($withBody) {
            $out["body_md"] = $row["body_md"];
            $out["body_html"] = $row["body_html"];
        }
        return $out;
    }
}
