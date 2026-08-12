<?php

/**
 * Post - helpers shared by questions, answers and comments.
 */
class Post {

    /** Bump the activity stamp of the question a post belongs to. */
    public static function touch(string $postType, int $postId, string $activity = "edited", ?int $userId = null): void {
        $db = new SQL(0);
        $questionId = $postType === "question" ? $postId : (int)$db->cmdvalue('SELECT question_id FROM answers WHERE id = "{0}"', [$postId]);
        if ($questionId <= 0) return;
        $db->cmd(
            'UPDATE questions SET last_activity_at = UTC_TIMESTAMP(), last_activity_by = {0}, last_activity_type = "{1}" WHERE id = "{2}"',
            [$userId ?? (MyUser::id() ?: "NULL"), $activity, $questionId]
        );
    }

    /** The question a post belongs to. */
    public static function questionId(string $postType, int $postId): int {
        if ($postType === "question") return $postId;
        $db = new SQL(0);
        if ($postType === "answer") return (int)$db->cmdvalue('SELECT question_id FROM answers WHERE id = "{0}"', [$postId]);
        $row = $db->cmdrow('SELECT post_type, post_id FROM comments WHERE id = "{0}"', [$postId]);
        if ($row === []) return 0;
        return self::questionId((string)$row["post_type"], (int)$row["post_id"]);
    }

    /** Canonical URL of any post. */
    public static function permalink(string $postType, int $postId): string {
        $questionId = self::questionId($postType, $postId);
        if ($questionId <= 0) return url("/");
        $base = Question::permalink($questionId);
        return match ($postType) {
            "question" => $base,
            "answer"   => $base . "#answer-" . $postId,
            "comment"  => $base . "#comment-" . $postId,
            default    => $base,
        };
    }

    public static function load(string $postType, int $postId): array {
        $db = new SQL(0);
        return $db->cmdrow('SELECT * FROM ' . Vote::table($postType) . ' WHERE id = "{0}" LIMIT 0,1', [$postId]);
    }
}
