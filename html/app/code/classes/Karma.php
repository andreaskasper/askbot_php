<?php

/**
 * Karma - reputation points.
 *
 * Every change is written to karma_log so a profile can show where the points
 * came from, and so a vote can be taken back cleanly.
 */
class Karma {

    /**
     * Award (or subtract) points. Returns the points that were actually
     * applied - the daily cap may reduce them to zero.
     */
    public static function award(int $userId, string $reason, int $points, string $postType = "none", ?int $postId = null, ?int $actorId = null): int {
        if ($userId <= 0 || $points === 0) return 0;

        $db = new SQL(0);

        // Positive points are capped per day, penalties always apply.
        if ($points > 0) {
            $cap = Config::int("karma_daily_cap", 200);
            if ($cap > 0) {
                $today = $db->cmdint(
                    'SELECT COALESCE(SUM(points),0) FROM karma_log WHERE user_id = "{0}" AND points > 0 AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) AND reason NOT IN ("accepted_answer","badge")',
                    [$userId]
                );
                if ($today >= $cap) return 0;
                if ($today + $points > $cap) $points = $cap - $today;
            }
        }

        $db->Create("karma_log", [
            "user_id"   => $userId,
            "reason"    => $reason,
            "points"    => $points,
            "post_type" => $postType,
            "post_id"   => $postId,
            "actor_id"  => $actorId,
        ]);
        // Karma never drops below 1 - that is the floor for a registered account.
        $db->cmd('UPDATE users SET karma = GREATEST(1, karma + ({0})) WHERE id = "{1}"', [SQL::int($points), $userId]);
        return $points;
    }

    /** Undo a previous award (used when a vote is retracted). */
    public static function revoke(int $userId, string $reason, string $postType, int $postId, ?int $actorId = null): void {
        $db = new SQL(0);
        $sql = 'SELECT COALESCE(SUM(points),0) FROM karma_log WHERE user_id = "{0}" AND reason = "{1}" AND post_type = "{2}" AND post_id = "{3}"';
        $values = [$userId, $reason, $postType, $postId];
        if ($actorId !== null) { $sql .= ' AND actor_id = "{4}"'; $values[] = $actorId; }
        $points = (int)$db->cmdvalue($sql, $values);
        if ($points === 0) return;

        $db->cmd('UPDATE users SET karma = GREATEST(1, karma - ({0})) WHERE id = "{1}"', [SQL::int($points), $userId]);
        $delete = 'DELETE FROM karma_log WHERE user_id = "{0}" AND reason = "{1}" AND post_type = "{2}" AND post_id = "{3}"';
        if ($actorId !== null) $delete .= ' AND actor_id = "{4}"';
        $db->cmd($delete, $values);
    }

    /** Recalculate a user's karma from the log - repair tool for the admin area. */
    public static function recalculate(int $userId): int {
        $db = new SQL(0);
        $sum = $db->cmdint('SELECT COALESCE(SUM(points),0) FROM karma_log WHERE user_id = "{0}"', [$userId]);
        $karma = max(1, $sum + Config::int("karma_new_user", 1));
        $db->Update("users", ["karma" => $karma], $userId);
        return $karma;
    }

    public static function history(int $userId, int $limit = 50, int $offset = 0): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT * FROM karma_log WHERE user_id = "{0}" ORDER BY id DESC LIMIT ' . SQL::int($offset) . ',' . SQL::int($limit),
            [$userId]
        );
    }

    /** Human readable label for the profile page. */
    public static function reasonLabel(string $reason): string {
        return match ($reason) {
            "question_upvote"   => __("Question upvoted"),
            "question_downvote" => __("Question downvoted"),
            "answer_upvote"     => __("Answer upvoted"),
            "answer_downvote"   => __("Answer downvoted"),
            "accepted_answer"   => __("Answer accepted"),
            "accept_answer"     => __("Accepted an answer"),
            "downvote_cost"     => __("Cast a downvote"),
            "badge"             => __("Badge awarded"),
            "moderation"        => __("Moderator adjustment"),
            default             => ucfirst(str_replace("_", " ", $reason)),
        };
    }
}
