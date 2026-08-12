<?php

/**
 * Permission - what a given user is allowed to do.
 *
 * Kept separate from MyUser so the same rules apply on the command line, in
 * tests and in background jobs, where there is no session.
 */
class Permission {

    /**
     * @param User|null  $user   the acting user, null for anonymous visitors
     * @param string     $action ask, answer, comment, vote_up, vote_down, flag,
     *                           edit, edit_others, close, delete, tag_wiki, …
     * @param array|null $post   post row, used for the ownership checks
     */
    public static function can(?User $user, string $action, ?array $post = null): bool {
        if ($user === null || !$user->exists()) return false;
        if ($user->isSuspended()) return false;
        if ($user->isStaff()) return true;

        $karma = (int)$user->karma;
        $ownPost = $post !== null && (int)($post["author_id"] ?? 0) === $user->id();

        return match ($action) {
            "ask", "answer" => true,
            "comment"       => $karma >= Config::int("threshold_comment", 1) || $ownPost,
            "vote_up"       => $karma >= Config::int("threshold_vote_up", 15),
            "vote_down"     => $karma >= Config::int("threshold_vote_down", 125),
            "flag"          => $karma >= Config::int("threshold_flag", 15),
            "edit_wiki"     => $karma >= Config::int("threshold_edit_wiki", 100),
            "close"         => $karma >= Config::int("threshold_close_vote", 500),
            "edit_others"   => $karma >= Config::int("threshold_edit_others", 2000),
            "tag_wiki"      => $karma >= Config::int("threshold_tag_wiki", 1500),
            "delete_vote"   => $karma >= Config::int("threshold_delete_vote", 3000),
            "edit"          => $ownPost || $karma >= Config::int("threshold_edit_others", 2000),
            "delete"        => $ownPost,
            default         => false,
        };
    }

    /** Karma still missing for an action, 0 when it is already allowed. */
    public static function karmaNeeded(?User $user, string $action): int {
        $needed = match ($action) {
            "comment"     => Config::int("threshold_comment", 1),
            "vote_up"     => Config::int("threshold_vote_up", 15),
            "vote_down"   => Config::int("threshold_vote_down", 125),
            "flag"        => Config::int("threshold_flag", 15),
            "edit_wiki"   => Config::int("threshold_edit_wiki", 100),
            "close"       => Config::int("threshold_close_vote", 500),
            "edit_others" => Config::int("threshold_edit_others", 2000),
            "tag_wiki"    => Config::int("threshold_tag_wiki", 1500),
            default       => 0,
        };
        return max(0, $needed - (int)($user?->karma ?? 0));
    }
}
