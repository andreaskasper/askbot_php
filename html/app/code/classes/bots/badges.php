<?php

namespace bots;

/**
 * badges - award the achievements that are too expensive to check inline.
 *
 * Schedule: every 10 minutes.
 */
class badges {

    public static function run(array $data = []): string {
        $db = new \SQL(0);
        $awarded = 0;

        // View count badges for questions that were read recently.
        $questions = $db->cmdrows(
            'SELECT id FROM questions WHERE deleted_at IS NULL AND view_count >= 250
             AND last_activity_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) LIMIT 500'
        );
        foreach ($questions as $row) {
            \Badge::checkPostBadges("question", (int)$row["id"]);
            $awarded++;
        }

        // Taxonomist: a tag the user created is used by 25 questions.
        $tags = $db->cmdrows('SELECT created_by FROM tags WHERE question_count >= 25 AND created_by IS NOT NULL');
        foreach ($tags as $row) {
            if (\Badge::awardKey((int)$row["created_by"], "taxonomist")) $awarded++;
        }

        // Necromancer: answered a very old question and got upvotes for it.
        $necro = $db->cmdrows(
            'SELECT a.id, a.author_id FROM answers a JOIN questions q ON q.id = a.question_id
             WHERE a.score >= 5 AND a.deleted_at IS NULL AND a.created_at > DATE_ADD(q.created_at, INTERVAL 60 DAY)
             ORDER BY a.id DESC LIMIT 200'
        );
        foreach ($necro as $row) {
            if (\Badge::awardKey((int)$row["author_id"], "necromancer", "answer", (int)$row["id"])) $awarded++;
        }

        // Populist: a non accepted answer that outscores the accepted one.
        $populist = $db->cmdrows(
            'SELECT a.id, a.author_id FROM answers a
             JOIN questions q ON q.id = a.question_id
             JOIN answers acc ON acc.id = q.accepted_answer_id
             WHERE a.is_accepted = 0 AND a.score >= acc.score + 10 AND a.deleted_at IS NULL LIMIT 200'
        );
        foreach ($populist as $row) {
            if (\Badge::awardKey((int)$row["author_id"], "populist", "answer", (int)$row["id"])) $awarded++;
        }

        // Visit streaks.
        $streaks = $db->cmdrows(
            'SELECT id FROM users WHERE last_seen_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY) AND deleted_at IS NULL LIMIT 500'
        );
        foreach ($streaks as $row) {
            $days = $db->cmdint(
                'SELECT COUNT(DISTINCT DATE(created_at)) FROM karma_log WHERE user_id = "{0}" AND created_at > DATE_SUB(UTC_DATE(), INTERVAL 30 DAY)',
                [(int)$row["id"]]
            );
            if ($days >= 30) \Badge::awardKey((int)$row["id"], "enthusiast");
            \Badge::checkUserBadges((int)$row["id"]);
        }

        return "checked " . count($questions) . " questions, " . $awarded . " new badges";
    }
}
