<?php

/**
 * Flag - "this post needs a moderator".
 */
class Flag {

    public const REASONS = [
        "spam"      => "Spam or advertising",
        "rude"      => "Rude or abusive",
        "duplicate" => "Duplicate question",
        "unclear"   => "Unclear or low quality",
        "offtopic"  => "Off topic",
        "other"     => "Something else",
    ];

    public static function raise(string $postType, int $postId, int $userId, string $reason, string $note = ""): void {
        if (!isset(self::REASONS[$reason])) throw new \InvalidArgumentException(__("Unknown reason."));
        if (!MyUser::can("flag")) throw new \RuntimeException(sprintf(__("You need %d karma to flag posts."), Config::int("threshold_flag", 15)));
        if (!RateLimiter::check("flag:" . $userId, 20, 86400)) throw new \RuntimeException(__("You have raised many flags today."));

        $db = new SQL(0);
        $db->CreateUpdate("flags", [
            "post_type" => $postType,
            "post_id"   => $postId,
            "user_id"   => $userId,
            "reason"    => $reason,
            "note"      => mb_substr($note, 0, 500),
            "status"    => "open",
        ], ["reason", "note", "status"]);

        // Enough open flags hide the post until a moderator looks at it.
        $open = $db->cmdint('SELECT COUNT(*) FROM flags WHERE post_type = "{0}" AND post_id = "{1}" AND status = "open"', [$postType, $postId]);
        if ($open >= Config::int("flags_needed_autohide", 5) && in_array($postType, ["question", "answer"], true)) {
            $db->cmd('UPDATE ' . Vote::table($postType) . ' SET is_spam = 1 WHERE id = "{0}"', [$postId]);
        }
        Audit::log("flag.raise", $postType . ":" . $postId, ["reason" => $reason], $userId);
    }

    public static function open(int $limit = 100): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT f.*, u.username FROM flags f LEFT JOIN users u ON u.id = f.user_id
             WHERE f.status = "open" ORDER BY f.created_at ASC LIMIT ' . SQL::int($limit)
        );
    }

    public static function handle(int $flagId, string $status, int $moderatorId): void {
        if (!in_array($status, ["accepted", "declined"], true)) throw new \InvalidArgumentException("Unknown status");
        $db = new SQL(0);
        $flag = $db->cmdrow('SELECT * FROM flags WHERE id = "{0}" LIMIT 0,1', [$flagId]);
        if ($flag === []) return;

        $db->Update("flags", [
            "status"     => $status,
            "handled_by" => $moderatorId,
            "handled_at" => gmdate("Y-m-d H:i:s"),
        ], $flagId);

        if ($status === "accepted") {
            Badge::awardKey((int)$flag["user_id"], "citizen_patrol");
        } elseif (in_array((string)$flag["post_type"], ["question", "answer"], true)) {
            $db->cmd('UPDATE ' . Vote::table((string)$flag["post_type"]) . ' SET is_spam = 0 WHERE id = "{0}"', [(int)$flag["post_id"]]);
        }
        Audit::log("flag.handle", "flag:" . $flagId, ["status" => $status], $moderatorId);
    }

    public static function countOpen(): int {
        $db = new SQL(0);
        return $db->cmdint('SELECT COUNT(*) FROM flags WHERE status = "open"');
    }
}
