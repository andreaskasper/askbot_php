<?php

namespace bots;

/**
 * digest - "interesting questions you might have missed" for people who
 * follow tags but have not been around for a while.
 *
 * Schedule: daily.
 */
class digest {

    public static function run(array $data = []): string {
        $db = new \SQL(0);
        $sent = 0;

        $users = $db->cmdrows(
            'SELECT DISTINCT u.id, u.email, u.username FROM users u
             JOIN subscriptions s ON s.user_id = u.id AND s.target_type = "tag"
             WHERE u.email_digest = "weekly" AND u.email_verified_at IS NOT NULL AND u.deleted_at IS NULL
               AND (u.last_seen_at IS NULL OR u.last_seen_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 DAY))
             LIMIT 200'
        );

        foreach ($users as $row) {
            $userId = (int)$row["id"];
            $questions = $db->cmdrows(
                'SELECT DISTINCT q.id, q.title, q.slug, q.answer_count, q.score FROM questions q
                 JOIN question_tags qt ON qt.question_id = q.id
                 JOIN subscriptions s ON s.target_type = "tag" AND s.target_id = qt.tag_id
                 WHERE s.user_id = "{0}" AND q.deleted_at IS NULL
                   AND q.created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
                 ORDER BY q.score DESC, q.answer_count DESC LIMIT 10',
                [$userId]
            );
            if ($questions === []) continue;

            \Mailer::queueTemplate(
                (string)$row["email"],
                (string)$row["username"],
                sprintf(\__("Questions from your tags this week on %s"), (string)\Config::get("site_title", "Askbot")),
                "weekly",
                ["questions" => $questions]
            );
            $sent++;
        }
        return "queued " . $sent . " weekly digests";
    }
}
