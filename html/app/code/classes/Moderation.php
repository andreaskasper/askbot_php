<?php

/**
 * Moderation - community close votes and moderator actions.
 */
class Moderation {

    public const CLOSE_REASONS = [
        "duplicate"  => "Duplicate of another question",
        "unclear"    => "Needs details or clarity",
        "opinion"    => "Opinion based",
        "broad"      => "Too broad",
        "offtopic"   => "Off topic",
    ];

    /**
     * Cast a close/reopen/delete vote. Moderators act immediately, everyone
     * else needs close_votes_needed votes.
     *
     * @return array{applied:bool,votes:int,needed:int}
     */
    public static function vote(int $questionId, int $userId, string $action, string $reason = "", ?int $duplicateOf = null): array {
        if (!in_array($action, ["close", "reopen", "delete"], true)) throw new \InvalidArgumentException("Unknown action");

        $question = new Question($questionId);
        if (!$question->exists()) throw new \InvalidArgumentException(__("This question no longer exists."));

        $needed = Config::int("close_votes_needed", 3);
        $db = new SQL(0);

        if (MyUser::isModerator()) {
            self::apply($question, $action, $reason, $userId, $duplicateOf);
            return ["applied" => true, "votes" => $needed, "needed" => $needed];
        }

        $permission = $action === "delete" ? "delete_vote" : "close";
        if (!MyUser::can($permission)) {
            throw new \RuntimeException(sprintf(__("You need %d karma for this."), Config::int($action === "delete" ? "threshold_delete_vote" : "threshold_close_vote", 500)));
        }

        $db->cmd(
            'INSERT IGNORE INTO close_votes (question_id, user_id, action, reason, duplicate_of_id) VALUES ("{0}", "{1}", "{2}", "{3}", {4})',
            [$questionId, $userId, $action, mb_substr($reason, 0, 64), $duplicateOf === null ? "NULL" : (int)$duplicateOf]
        );
        $votes = $db->cmdint('SELECT COUNT(*) FROM close_votes WHERE question_id = "{0}" AND action = "{1}"', [$questionId, $action]);

        if ($votes >= $needed) {
            self::apply($question, $action, $reason, $userId, $duplicateOf);
            $db->cmd('DELETE FROM close_votes WHERE question_id = "{0}" AND action = "{1}"', [$questionId, $action]);
            return ["applied" => true, "votes" => $votes, "needed" => $needed];
        }
        return ["applied" => false, "votes" => $votes, "needed" => $needed];
    }

    private static function apply(Question $question, string $action, string $reason, int $userId, ?int $duplicateOf): void {
        match ($action) {
            "close"  => $question->close($reason !== "" ? $reason : "unclear", $userId, $duplicateOf),
            "reopen" => $question->reopen($userId),
            "delete" => $question->softDelete($userId),
        };
    }

    /** Suspend a user for a number of days (0 = indefinitely). */
    public static function suspend(int $userId, int $days, string $reason, int $moderatorId): void {
        $user = new User($userId);
        if (!$user->exists()) return;
        if ($user->isStaff()) throw new \RuntimeException(__("Moderators cannot be suspended here."));
        $user->save([
            "is_suspended"     => 1,
            "suspended_until"  => $days > 0 ? gmdate("Y-m-d H:i:s", time() + $days * 86400) : null,
            "suspended_reason" => mb_substr($reason, 0, 255),
        ]);
        Notification::create($userId, "moderation", $moderatorId, "none", null, __("Your account has been suspended"));
        Audit::log("user.suspend", "user:" . $userId, ["days" => $days, "reason" => $reason], $moderatorId);
    }

    public static function unsuspend(int $userId, int $moderatorId): void {
        (new User($userId))->save(["is_suspended" => 0, "suspended_until" => null, "suspended_reason" => ""]);
        Audit::log("user.unsuspend", "user:" . $userId, [], $moderatorId);
    }

    public static function setRole(int $userId, string $role, int $moderatorId): void {
        if (!in_array($role, ["user", "moderator", "admin"], true)) throw new \InvalidArgumentException("Unknown role");
        (new User($userId))->save(["role" => $role]);
        Audit::log("user.role", "user:" . $userId, ["role" => $role], $moderatorId);
    }

    /** Everything a moderator should look at, for the dashboard. */
    public static function queue(): array {
        $db = new SQL(0);
        return [
            "flags"        => Flag::open(50),
            "spam_posts"   => $db->cmdrows('SELECT id, title, score, created_at FROM questions WHERE is_spam = 1 AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 50'),
            "close_votes"  => $db->cmdrows(
                'SELECT cv.question_id, cv.action, COUNT(*) AS votes, q.title FROM close_votes cv
                 JOIN questions q ON q.id = cv.question_id GROUP BY cv.question_id, cv.action, q.title ORDER BY votes DESC LIMIT 50'
            ),
            "new_users"    => $db->cmdrows('SELECT id, username, slug, email, karma, created_at FROM users ORDER BY id DESC LIMIT 20'),
            "suspended"    => $db->cmdrows('SELECT id, username, slug, suspended_until, suspended_reason FROM users WHERE is_suspended = 1'),
        ];
    }
}
