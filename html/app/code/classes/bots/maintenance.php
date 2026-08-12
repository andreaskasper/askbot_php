<?php

namespace bots;

/**
 * maintenance - keep the denormalised counters honest and expire bounties.
 *
 * Schedule: daily.
 */
class maintenance {

    public static function run(array $data = []): string {
        $db = new \SQL(0);

        $db->cmd('UPDATE questions q SET answer_count = (SELECT COUNT(*) FROM answers a WHERE a.question_id = q.id AND a.deleted_at IS NULL)');
        $questions = $db->affected();

        $db->cmd('UPDATE questions q SET comment_count = (SELECT COUNT(*) FROM comments c WHERE c.post_type = "question" AND c.post_id = q.id AND c.deleted_at IS NULL)');
        $db->cmd('UPDATE answers a SET comment_count = (SELECT COUNT(*) FROM comments c WHERE c.post_type = "answer" AND c.post_id = a.id AND c.deleted_at IS NULL)');

        $db->cmd('UPDATE users u SET question_count = (SELECT COUNT(*) FROM questions q WHERE q.author_id = u.id AND q.deleted_at IS NULL)');
        $db->cmd('UPDATE users u SET answer_count = (SELECT COUNT(*) FROM answers a WHERE a.author_id = u.id AND a.deleted_at IS NULL)');
        $db->cmd('UPDATE users u SET accepted_count = (SELECT COUNT(*) FROM answers a WHERE a.author_id = u.id AND a.is_accepted = 1 AND a.deleted_at IS NULL)');

        $db->cmd('UPDATE tags t SET question_count = (
                    SELECT COUNT(*) FROM question_tags qt JOIN questions q ON q.id = qt.question_id
                    WHERE qt.tag_id = t.id AND q.deleted_at IS NULL)');
        $tags = $db->affected();

        // Scores are the source of truth in the votes table.
        $db->cmd('UPDATE questions q SET score = (SELECT COALESCE(SUM(value),0) FROM votes v WHERE v.post_type = "question" AND v.post_id = q.id)');
        $db->cmd('UPDATE answers a SET score = (SELECT COALESCE(SUM(value),0) FROM votes v WHERE v.post_type = "answer" AND v.post_id = a.id)');
        $db->cmd('UPDATE comments c SET score = (SELECT COALESCE(SUM(value),0) FROM votes v WHERE v.post_type = "comment" AND v.post_id = c.id)');

        // Expired bounties are simply dropped - the karma stays with the asker.
        $db->cmd('UPDATE questions SET bounty_amount = 0, bounty_expires_at = NULL WHERE bounty_expires_at IS NOT NULL AND bounty_expires_at < UTC_TIMESTAMP()');
        $bounties = $db->affected();

        \WebCache::flush();
        return "recounted " . $questions . " questions, " . $tags . " tags, expired " . $bounties . " bounties";
    }
}
