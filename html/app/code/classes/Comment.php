<?php

/**
 * Comment - short remarks below a question or an answer.
 */
class Comment {

    public const MAX_LENGTH = 1000;

    public static function create(string $postType, int $postId, string $bodyMd, int $authorId): array {
        $bodyMd = trim($bodyMd);
        if (!in_array($postType, ["question", "answer"], true)) throw new \InvalidArgumentException("Unknown post type");
        if (mb_strlen($bodyMd) < 2) throw new \InvalidArgumentException(__("Your comment is too short."));
        if (mb_strlen($bodyMd) > self::MAX_LENGTH) throw new \InvalidArgumentException(sprintf(__("Comments are limited to %d characters."), self::MAX_LENGTH));

        $post = Post::load($postType, $postId);
        if ($post === [] || $post["deleted_at"] !== null) throw new \InvalidArgumentException(__("This post no longer exists."));
        if (!Permission::can(new User($authorId), "comment", $post)) throw new \RuntimeException(sprintf(__("You need %d karma to comment."), Config::int("threshold_comment", 1)));
        if (!RateLimiter::check("comment:" . $authorId, 60, 3600)) throw new \RuntimeException(__("You are commenting too fast."));

        $db = new SQL(0);
        $id = $db->Create("comments", [
            "post_type" => $postType,
            "post_id"   => $postId,
            "author_id" => $authorId,
            "body_md"   => $bodyMd,
            "body_html" => Markdown::render($bodyMd),
        ]);
        $db->cmd('UPDATE ' . Vote::table($postType) . ' SET comment_count = comment_count + 1 WHERE id = "{0}"', [$postId]);
        Post::touch($postType, $postId, "commented", $authorId);
        Notification::forNewComment($postType, $postId, $id, $authorId, $bodyMd);
        Badge::awardKey($authorId, "first_comment");

        return self::one($id);
    }

    public static function one(int $id): array {
        $db = new SQL(0);
        $row = $db->cmdrow(
            'SELECT c.*, u.username, u.slug AS user_slug, u.karma FROM comments c LEFT JOIN users u ON u.id = c.author_id WHERE c.id = "{0}" LIMIT 0,1',
            [$id]
        );
        return $row;
    }

    public static function forPost(string $postType, int $postId): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT c.*, u.username, u.slug AS user_slug, u.karma FROM comments c LEFT JOIN users u ON u.id = c.author_id
             WHERE c.post_type = "{0}" AND c.post_id = "{1}" AND c.deleted_at IS NULL ORDER BY c.created_at ASC',
            [$postType, $postId]
        );
    }

    /** Comments for many posts at once: [postId => rows]. */
    public static function forPosts(string $postType, array $postIds): array {
        $postIds = array_values(array_filter(array_map("intval", $postIds)));
        if ($postIds === []) return [];
        $db = new SQL(0);
        $rows = $db->cmdrows(
            'SELECT c.*, u.username, u.slug AS user_slug, u.karma FROM comments c LEFT JOIN users u ON u.id = c.author_id
             WHERE c.post_type = "{0}" AND c.post_id IN (' . implode(",", $postIds) . ') AND c.deleted_at IS NULL ORDER BY c.created_at ASC',
            [$postType]
        );
        $out = [];
        foreach ($rows as $row) $out[(int)$row["post_id"]][] = $row;
        return $out;
    }

    public static function delete(int $id, int $userId): void {
        $db = new SQL(0);
        $row = $db->cmdrow('SELECT * FROM comments WHERE id = "{0}" LIMIT 0,1', [$id]);
        if ($row === []) return;
        if ((int)$row["author_id"] !== $userId && !(new User($userId))->isStaff()) {
            throw new \RuntimeException(__("You may only delete your own comments."));
        }
        $db->Update("comments", ["deleted_at" => gmdate("Y-m-d H:i:s")], $id);
        $db->cmd('UPDATE ' . Vote::table((string)$row["post_type"]) . ' SET comment_count = GREATEST(0, comment_count - 1) WHERE id = "{0}"', [(int)$row["post_id"]]);
        Audit::log("comment.delete", "comment:" . $id, [], $userId);
    }

    public static function toArray(array $row): array {
        return [
            "id"         => (int)$row["id"],
            "body_html"  => $row["body_html"],
            "body_md"    => $row["body_md"],
            "score"      => (int)$row["score"],
            "created_at" => $row["created_at"],
            "author"     => [
                "id"       => (int)$row["author_id"],
                "username" => $row["username"] ?? __("deleted user"),
                "url"      => isset($row["author_id"]) ? url("users/" . (int)$row["author_id"] . "/" . ($row["user_slug"] ?? "")) : "",
                "karma"    => (int)($row["karma"] ?? 0),
            ],
        ];
    }
}
