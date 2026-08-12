<?php

/**
 * Statistics - numbers for the admin dashboard and the sidebar.
 */
class Statistics {

    public static function overview(): array {
        return WebCache::remember("stats:overview", 300, function () {
            $db = new SQL(0);
            return [
                "questions"        => $db->cmdint('SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL'),
                "answers"          => $db->cmdint('SELECT COUNT(*) FROM answers WHERE deleted_at IS NULL'),
                "comments"         => $db->cmdint('SELECT COUNT(*) FROM comments WHERE deleted_at IS NULL'),
                "users"            => $db->cmdint('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
                "tags"             => $db->cmdint('SELECT COUNT(*) FROM tags WHERE question_count > 0'),
                "unanswered"       => $db->cmdint('SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL AND answer_count = 0'),
                "accepted_rate"    => self::acceptedRate(),
                "active_today"     => $db->cmdint('SELECT COUNT(*) FROM users WHERE last_seen_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)'),
                "questions_7d"     => $db->cmdint('SELECT COUNT(*) FROM questions WHERE created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)'),
                "answers_7d"       => $db->cmdint('SELECT COUNT(*) FROM answers WHERE created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)'),
                "open_flags"       => Flag::countOpen(),
                "pending_mails"    => $db->cmdint('SELECT COUNT(*) FROM mail_queue WHERE status = "pending"'),
            ];
        });
    }

    private static function acceptedRate(): float {
        $db = new SQL(0);
        $total = $db->cmdint('SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL');
        if ($total === 0) return 0.0;
        $accepted = $db->cmdint('SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL AND accepted_answer_id IS NOT NULL');
        return round($accepted * 100 / $total, 1);
    }

    /** Daily counts for the last $days days, ready for a chart. */
    public static function timeline(int $days = 30): array {
        $db = new SQL(0);
        $questions = $db->cmdrows(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM questions WHERE created_at > DATE_SUB(UTC_DATE(), INTERVAL ' . SQL::int($days) . ' DAY) GROUP BY DATE(created_at)',
            [], "d"
        );
        $answers = $db->cmdrows(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM answers WHERE created_at > DATE_SUB(UTC_DATE(), INTERVAL ' . SQL::int($days) . ' DAY) GROUP BY DATE(created_at)',
            [], "d"
        );
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = gmdate("Y-m-d", time() - $i * 86400);
            $out[] = [
                "date"      => $day,
                "questions" => (int)($questions[$day]["c"] ?? 0),
                "answers"   => (int)($answers[$day]["c"] ?? 0),
            ];
        }
        return $out;
    }

    public static function topUsers(int $limit = 10, string $period = "all"): array {
        $db = new SQL(0);
        if ($period === "month") {
            return $db->cmdrows(
                'SELECT u.id, u.username, u.slug, u.karma, SUM(k.points) AS period_karma
                 FROM karma_log k JOIN users u ON u.id = k.user_id
                 WHERE k.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)
                 GROUP BY u.id, u.username, u.slug, u.karma ORDER BY period_karma DESC LIMIT ' . SQL::int($limit)
            );
        }
        return $db->cmdrows(
            'SELECT id, username, slug, karma, question_count, answer_count FROM users
             WHERE deleted_at IS NULL ORDER BY karma DESC LIMIT ' . SQL::int($limit)
        );
    }
}
