<?php

/**
 * Badge - achievements.
 *
 * Cheap checks run inline (right after the action that could earn a badge),
 * expensive ones are handled by the "badges" bot.
 */
class Badge {

    /** Award a badge by its key. Returns true when it was actually new. */
    public static function awardKey(int $userId, string $key, string $postType = "none", ?int $postId = null): bool {
        if ($userId <= 0) return false;
        $db = new SQL(0);
        $badge = $db->cmdrow('SELECT * FROM badges WHERE key_name = "{0}" LIMIT 0,1', [$key]);
        if ($badge === []) return false;

        // Single award badges are only ever given once.
        if ((int)$badge["is_multiple"] === 0) {
            $exists = $db->cmdvalue('SELECT id FROM user_badges WHERE user_id = "{0}" AND badge_id = "{1}" LIMIT 0,1', [$userId, (int)$badge["id"]]);
            if ($exists !== null) return false;
            $postType = "none";
            $postId = null;
        }

        $db->cmd(
            'INSERT IGNORE INTO user_badges (user_id, badge_id, post_type, post_id) VALUES ("{0}", "{1}", "{2}", {3})',
            [$userId, (int)$badge["id"], $postType, $postId === null ? "NULL" : (int)$postId]
        );
        if ($db->affected() === 0) return false;

        $db->cmd('UPDATE badges SET awarded_count = awarded_count + 1 WHERE id = "{0}"', [(int)$badge["id"]]);
        Notification::create($userId, "badge", null, $postType, $postId,
            sprintf(__("You earned the %s badge"), (string)$badge["name"]), url("users/" . $userId . "/x/badges"));
        return true;
    }

    /** Score and view based badges for one post. */
    public static function checkPostBadges(string $postType, int $postId): void {
        $db = new SQL(0);
        $post = Post::load($postType, $postId);
        if ($post === []) return;
        $authorId = (int)$post["author_id"];
        if ($authorId <= 0) return;
        $score = (int)$post["score"];

        if ($postType === "question") {
            if ($score >= 1)  self::awardKey($authorId, "student", "question", $postId);
            if ($score >= 5)  self::awardKey($authorId, "nice_question", "question", $postId);
            if ($score >= 15) self::awardKey($authorId, "good_question", "question", $postId);
            if ($score >= 40) self::awardKey($authorId, "great_question", "question", $postId);

            $views = (int)$post["view_count"];
            if ($views >= 250)  self::awardKey($authorId, "popular_question", "question", $postId);
            if ($views >= 1000) self::awardKey($authorId, "notable_question", "question", $postId);
            if ($views >= 5000) self::awardKey($authorId, "famous_question", "question", $postId);
        }

        if ($postType === "answer") {
            if ($score >= 1)  self::awardKey($authorId, "teacher", "answer", $postId);
            if ($score >= 5)  self::awardKey($authorId, "nice_answer", "answer", $postId);
            if ($score >= 15) self::awardKey($authorId, "good_answer", "answer", $postId);
            if ($score >= 40) self::awardKey($authorId, "great_answer", "answer", $postId);

            if ((int)$post["is_accepted"] === 1) {
                if ($score >= 10) self::awardKey($authorId, "enlightened", "answer", $postId);
                if ($score >= 30) self::awardKey($authorId, "guru", "answer", $postId);
            }
            $questionAuthor = (int)$db->cmdvalue('SELECT author_id FROM questions WHERE id = "{0}"', [(int)$post["question_id"]]);
            if ($questionAuthor === $authorId && $score >= 3) {
                self::awardKey($authorId, "self_learner", "answer", $postId);
            }
        }
    }

    /** Profile and counter based badges. */
    public static function checkUserBadges(int $userId): void {
        if ($userId <= 0) return;
        $db = new SQL(0);
        $user = new User($userId);
        if (!$user->exists()) return;

        if ((int)$user->question_count >= 1) self::awardKey($userId, "first_question");
        if ((int)$user->answer_count >= 1)   self::awardKey($userId, "first_answer");
        if (trim((string)$user->bio_md) !== "") self::awardKey($userId, "autobiographer");

        $votes = $db->cmdint('SELECT COUNT(*) FROM votes WHERE user_id = "{0}"', [$userId]);
        if ($votes >= 1)   self::awardKey($userId, "first_vote");
        if ($votes >= 100) self::awardKey($userId, "civic_duty");

        $downVotes = $db->cmdint('SELECT COUNT(*) FROM votes WHERE user_id = "{0}" AND value < 0', [$userId]);
        if ($downVotes >= 1) self::awardKey($userId, "critic");

        $comments = $db->cmdint('SELECT COUNT(*) FROM comments WHERE author_id = "{0}" AND deleted_at IS NULL', [$userId]);
        if ($comments >= 1) self::awardKey($userId, "first_comment");

        $goodComments = $db->cmdint('SELECT COUNT(*) FROM comments WHERE author_id = "{0}" AND score >= 5', [$userId]);
        if ($goodComments >= 10) self::awardKey($userId, "pundit");
    }

    public static function forUser(int $userId): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT b.*, ub.awarded_at, ub.post_type, ub.post_id FROM user_badges ub
             JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = "{0}" ORDER BY ub.awarded_at DESC',
            [$userId]
        );
    }

    public static function all(): array {
        $db = new SQL(0);
        return $db->cmdrows('SELECT * FROM badges ORDER BY FIELD(level, "gold", "silver", "bronze"), name');
    }

    public static function one(int $id): array {
        $db = new SQL(0);
        return $db->cmdrow('SELECT * FROM badges WHERE id = "{0}" LIMIT 0,1', [$id]);
    }

    public static function recipients(int $badgeId, int $limit = 100): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT u.id, u.username, u.slug, u.karma, ub.awarded_at FROM user_badges ub
             JOIN users u ON u.id = ub.user_id WHERE ub.badge_id = "{0}" ORDER BY ub.awarded_at DESC LIMIT ' . SQL::int($limit),
            [$badgeId]
        );
    }

    public static function levelClass(string $level): string {
        return match ($level) {
            "gold"   => "badge-gold",
            "silver" => "badge-silver",
            default  => "badge-bronze",
        };
    }
}
