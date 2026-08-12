<?php

/**
 * Notification - the bell menu and the mail digest feed it.
 */
class Notification {

    public static function create(int $userId, string $type, ?int $actorId, string $postType, ?int $postId, string $title, string $url = ""): int {
        if ($userId <= 0 || $userId === $actorId) return 0;
        $db = new SQL(0);
        if ($url === "" && $postId !== null && $postType !== "none") {
            $url = Post::permalink($postType, $postId);
        }
        return $db->Create("notifications", [
            "user_id"   => $userId,
            "type"      => $type,
            "actor_id"  => $actorId,
            "post_type" => $postType,
            "post_id"   => $postId,
            "title"     => mb_substr($title, 0, 255),
            "url"       => mb_substr($url, 0, 500),
        ]);
    }

    /** Everyone following the question hears about a new answer. */
    public static function forNewAnswer(int $questionId, int $answerId, int $actorId): void {
        $db = new SQL(0);
        $question = $db->cmdrow('SELECT id, title, author_id FROM questions WHERE id = "{0}"', [$questionId]);
        if ($question === []) return;
        $actor = (new User($actorId))->displayName();
        $title = sprintf(__("%s answered: %s"), $actor, mb_substr((string)$question["title"], 0, 120));

        foreach (Subscription::subscribers("question", $questionId) as $userId) {
            self::create($userId, "new_answer", $actorId, "answer", $answerId, $title);
        }
        // Tag followers only get told about brand new questions, not answers.
    }

    public static function forNewQuestion(int $questionId, int $actorId, array $tagNames): void {
        $db = new SQL(0);
        $question = $db->cmdrow('SELECT id, title FROM questions WHERE id = "{0}"', [$questionId]);
        if ($question === []) return;
        $title = sprintf(__("New question: %s"), mb_substr((string)$question["title"], 0, 120));

        $recipients = [];
        foreach ($tagNames as $name) {
            $tagId = Tag::id($name, false);
            if ($tagId === 0) continue;
            foreach (Subscription::subscribers("tag", $tagId) as $userId) $recipients[$userId] = true;
        }
        foreach (array_keys($recipients) as $userId) {
            self::create((int)$userId, "new_question", $actorId, "question", $questionId, $title);
        }
    }

    public static function forNewComment(string $postType, int $postId, int $commentId, int $actorId, string $body): void {
        $post = Post::load($postType, $postId);
        if ($post === []) return;
        $actor = (new User($actorId))->displayName();
        $title = sprintf(__("%s commented: %s"), $actor, mb_substr($body, 0, 100));

        self::create((int)$post["author_id"], "new_comment", $actorId, "comment", $commentId, $title);

        // @mentions in the comment
        if (preg_match_all('/(?:^|\s)@([A-Za-z0-9_.-]{2,64})/', $body, $matches)) {
            foreach (array_unique($matches[1]) as $username) {
                $user = User::byUsername($username);
                if ($user !== null && $user->id() !== (int)$post["author_id"]) {
                    self::create($user->id(), "mention", $actorId, "comment", $commentId, $title);
                }
            }
        }
    }

    public static function forUser(int $userId, int $limit = 30, bool $unreadOnly = false): array {
        $db = new SQL(0);
        $where = $unreadOnly ? " AND n.read_at IS NULL" : "";
        return $db->cmdrows(
            'SELECT n.*, u.username AS actor_name FROM notifications n LEFT JOIN users u ON u.id = n.actor_id
             WHERE n.user_id = "{0}"' . $where . ' ORDER BY n.id DESC LIMIT ' . SQL::int($limit),
            [$userId]
        );
    }

    public static function markRead(int $userId, ?int $id = null): int {
        $db = new SQL(0);
        if ($id === null) {
            $db->cmd('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = "{0}" AND read_at IS NULL', [$userId]);
        } else {
            $db->cmd('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE id = "{0}" AND user_id = "{1}"', [$id, $userId]);
        }
        return $db->affected();
    }

    public static function unreadCount(int $userId): int {
        if ($userId <= 0) return 0;
        $db = new SQL(0);
        return $db->cmdint('SELECT COUNT(*) FROM notifications WHERE user_id = "{0}" AND read_at IS NULL', [$userId]);
    }

    public static function icon(string $type): string {
        return match ($type) {
            "new_answer"      => "fa-comment-dots",
            "new_comment"     => "fa-comment",
            "answer_accepted" => "fa-circle-check",
            "mention"         => "fa-at",
            "badge"           => "fa-award",
            "question_closed" => "fa-lock",
            "post_edited"     => "fa-pen",
            "moderation"      => "fa-shield-halved",
            default           => "fa-bell",
        };
    }
}
