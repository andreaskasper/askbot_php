<?php

/**
 * MyUser - the user behind the current request.
 *
 * Everything is static because there is exactly one of them per request:
 *
 *     if (MyUser::can("vote_up")) { ... }
 */
class MyUser {

    private static ?User $_user = null;
    private static bool $_loaded = false;

    /** Restore the session (or the "remember me" cookie). */
    public static function load(): void {
        if (self::$_loaded) return;
        self::$_loaded = true;

        $id = (int)($_SESSION["user_id"] ?? 0);
        if ($id === 0 && !empty($_COOKIE["askbot_remember"])) {
            $id = self::fromRememberCookie((string)$_COOKIE["askbot_remember"]);
        }
        if ($id === 0) return;

        $user = new User($id);
        if (!$user->exists() || $user->deleted_at !== null) { self::logout(); return; }
        self::$_user = $user;

        // "last seen" is only worth one write per five minutes.
        $lastSeen = strtotime((string)$user->last_seen_at . " UTC");
        if ($lastSeen === false || time() - $lastSeen > 300) {
            $user->save(["last_seen_at" => gmdate("Y-m-d H:i:s"), "last_ip_hash" => Firewall::ipHash()]);
        }
    }

    public static function user(): ?User { return self::$_user; }
    public static function id(): int { return self::$_user?->id() ?? 0; }
    public static function isLoggedIn(): bool { return self::$_user !== null; }
    public static function karma(): int { return (int)(self::$_user?->karma ?? 0); }
    public static function name(): string { return self::$_user?->displayName() ?? ""; }

    public static function isAdmin(): bool {
        return self::$_user !== null && (string)self::$_user->role === "admin";
    }

    public static function isModerator(): bool {
        return self::$_user !== null && in_array((string)self::$_user->role, ["admin", "moderator"], true);
    }

    public static function login(User $user, bool $remember = false): void {
        Session::regenerate();
        $_SESSION["user_id"] = $user->id();
        unset($_SESSION["pending_2fa_user"]);
        self::$_user = $user;
        self::$_loaded = true;
        if ($remember) self::setRememberCookie($user);
        Audit::log("signin", "user:" . $user->id());
    }

    public static function logout(): void {
        if (!empty($_COOKIE["askbot_remember"])) {
            $parts = explode(":", (string)$_COOKIE["askbot_remember"], 2);
            if (count($parts) === 2) {
                $db = new SQL(0);
                $db->cmd('DELETE FROM user_tokens WHERE type = "remember" AND token_hash = "{0}"', [hash("sha256", $parts[1])]);
            }
            setcookie("askbot_remember", "", time() - 3600, "/");
        }
        self::$_user = null;
        Session::destroy();
    }

    // -----------------------------------------------------------------------
    // Permissions
    // -----------------------------------------------------------------------

    /**
     * Karma gate for the user of this request. The rules themselves live in
     * Permission so the command line and the tests can use them too.
     *
     * @param string     $action e.g. "comment", "vote_up", "close", "edit_others"
     * @param array|null $post   optional post row for ownership checks
     */
    public static function can(string $action, ?array $post = null): bool {
        if ($action === "accept" && $post !== null) {
            return (int)($post["question_author_id"] ?? 0) === self::id() || self::isModerator();
        }
        return Permission::can(self::$_user, $action, $post);
    }

    /** Karma still missing for an action, 0 when it is allowed. */
    public static function karmaNeeded(string $action): int {
        return Permission::karmaNeeded(self::$_user, $action);
    }

    // -----------------------------------------------------------------------
    // "Remember me"
    // -----------------------------------------------------------------------

    private static function setRememberCookie(User $user): void {
        $token = bin2hex(random_bytes(32));
        $db = new SQL(0);
        $db->Create("user_tokens", [
            "user_id"    => $user->id(),
            "type"       => "remember",
            "token_hash" => hash("sha256", $token),
            "expires_at" => gmdate("Y-m-d H:i:s", time() + 60 * 86400),
        ]);
        $secure = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
        setcookie("askbot_remember", $user->id() . ":" . $token, [
            "expires"  => time() + 60 * 86400,
            "path"     => "/",
            "secure"   => $secure,
            "httponly" => true,
            "samesite" => "Lax",
        ]);
    }

    private static function fromRememberCookie(string $cookie): int {
        $parts = explode(":", $cookie, 2);
        if (count($parts) !== 2) return 0;
        $db = new SQL(0);
        $row = $db->cmdrow(
            'SELECT user_id FROM user_tokens WHERE type = "remember" AND token_hash = "{0}" AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) LIMIT 0,1',
            [hash("sha256", $parts[1])]
        );
        if ($row === [] || (int)$row["user_id"] !== (int)$parts[0]) return 0;
        $_SESSION["user_id"] = (int)$row["user_id"];
        return (int)$row["user_id"];
    }
}
