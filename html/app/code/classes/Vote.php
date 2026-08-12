<?php

/**
 * Vote - up and down votes on questions, answers and comments.
 *
 * Voting the same way twice retracts the vote, which is what users expect.
 */
class Vote {

    /**
     * @return array{score:int,value:int} new score and the caller's vote value
     * @throws \RuntimeException when the vote is not allowed
     */
    public static function cast(string $postType, int $postId, int $value): array {
        if (!in_array($postType, ["question", "answer", "comment"], true)) {
            throw new \RuntimeException("Unknown post type");
        }
        $value = $value > 0 ? 1 : ($value < 0 ? -1 : 0);
        $userId = MyUser::id();
        if ($userId === 0) throw new \RuntimeException(__("Please sign in to vote."));

        $post = self::post($postType, $postId);
        if ($post === []) throw new \RuntimeException(__("This post no longer exists."));
        if ((int)$post["author_id"] === $userId) throw new \RuntimeException(__("You cannot vote on your own post."));

        if ($value > 0 && !MyUser::can("vote_up")) {
            throw new \RuntimeException(sprintf(__("You need %d karma to upvote."), Config::int("threshold_vote_up", 15)));
        }
        if ($value < 0 && !MyUser::can("vote_down")) {
            throw new \RuntimeException(sprintf(__("You need %d karma to downvote."), Config::int("threshold_vote_down", 125)));
        }
        if (!RateLimiter::check("vote:" . $userId, 200, 3600)) {
            throw new \RuntimeException(__("You have voted a lot today. Please come back later."));
        }

        $db = new SQL(0);
        $existing = $db->cmdrow(
            'SELECT * FROM votes WHERE post_type = "{0}" AND post_id = "{1}" AND user_id = "{2}" LIMIT 0,1',
            [$postType, $postId, $userId]
        );
        $old = $existing === [] ? 0 : (int)$existing["value"];
        $new = ($old === $value) ? 0 : $value;   // clicking the same arrow again retracts

        $db->begin();
        try {
            if ($new === 0) {
                $db->cmd('DELETE FROM votes WHERE post_type = "{0}" AND post_id = "{1}" AND user_id = "{2}"', [$postType, $postId, $userId]);
            } else {
                $db->CreateUpdate("votes", [
                    "post_type" => $postType,
                    "post_id"   => $postId,
                    "user_id"   => $userId,
                    "value"     => $new,
                ], ["value"]);
            }

            $delta = $new - $old;
            $table = self::table($postType);
            $db->cmd('UPDATE ' . $table . ' SET score = score + ({0}) WHERE id = "{1}"', [SQL::int($delta), $postId]);

            self::applyKarma($postType, $postId, (int)$post["author_id"], $userId, $old, $new);

            $score = $db->cmdint('SELECT score FROM ' . $table . ' WHERE id = "{0}"', [$postId]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }

        if ($postType !== "comment") {
            Post::touch($postType, $postId, "voted");
            Badge::checkPostBadges($postType, $postId);
        }
        return ["score" => $score, "value" => $new];
    }

    /** Karma bookkeeping for a vote change from $old to $new. */
    private static function applyKarma(string $postType, int $postId, int $authorId, int $voterId, int $old, int $new): void {
        if ($postType === "comment" || $authorId <= 0 || $old === $new) return;

        $upReason   = $postType . "_upvote";
        $downReason = $postType . "_downvote";
        $upPoints   = Config::int($postType === "question" ? "karma_question_upvote" : "karma_answer_upvote", 5);
        $downPoints = Config::int($postType === "question" ? "karma_question_downvote" : "karma_answer_downvote", -2);
        $costPoints = Config::int("karma_downvote_cost", -1);

        if ($old > 0) Karma::revoke($authorId, $upReason, $postType, $postId, $voterId);
        if ($old < 0) {
            Karma::revoke($authorId, $downReason, $postType, $postId, $voterId);
            Karma::revoke($voterId, "downvote_cost", $postType, $postId, $voterId);
        }
        if ($new > 0) Karma::award($authorId, $upReason, $upPoints, $postType, $postId, $voterId);
        if ($new < 0) {
            Karma::award($authorId, $downReason, $downPoints, $postType, $postId, $voterId);
            Karma::award($voterId, "downvote_cost", $costPoints, $postType, $postId, $voterId);
        }
    }

    /** The current user's vote on a set of posts: [postId => value]. */
    public static function myVotes(string $postType, array $postIds): array {
        $userId = MyUser::id();
        $postIds = array_values(array_filter(array_map("intval", $postIds)));
        if ($userId === 0 || $postIds === []) return [];
        $db = new SQL(0);
        $rows = $db->cmdrows(
            'SELECT post_id, value FROM votes WHERE post_type = "{0}" AND user_id = "{1}" AND post_id IN (' . implode(",", $postIds) . ')',
            [$postType, $userId]
        );
        $out = [];
        foreach ($rows as $row) $out[(int)$row["post_id"]] = (int)$row["value"];
        return $out;
    }

    public static function table(string $postType): string {
        return match ($postType) {
            "question" => "questions",
            "answer"   => "answers",
            "comment"  => "comments",
            default    => throw new \RuntimeException("Unknown post type"),
        };
    }

    private static function post(string $postType, int $postId): array {
        $db = new SQL(0);
        return $db->cmdrow('SELECT * FROM ' . self::table($postType) . ' WHERE id = "{0}" AND deleted_at IS NULL LIMIT 0,1', [$postId]);
    }
}
