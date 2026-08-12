<?php

/**
 * Session - cookie session with sane security defaults.
 */
class Session {

    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (PHP_SAPI === "cli") { $_SESSION = []; return; }

        $secure = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
               || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https");

        session_name("askbot");
        session_set_cookie_params([
            "lifetime" => 0,
            "path"     => "/",
            "domain"   => "",
            "secure"   => $secure,
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        ini_set("session.use_strict_mode", "1");
        ini_set("session.use_only_cookies", "1");
        session_start();

        // Rotate the id once per hour and on privilege changes.
        if (!isset($_SESSION["created_at"])) {
            $_SESSION["created_at"] = time();
        } elseif (time() - (int)$_SESSION["created_at"] > 3600) {
            session_regenerate_id(true);
            $_SESSION["created_at"] = time();
        }
    }

    public static function regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION["created_at"] = time();
        }
    }

    public static function destroy(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $p = session_get_cookie_params();
            setcookie(session_name(), "", time() - 42000, $p["path"], $p["domain"], $p["secure"], $p["httponly"]);
        }
        session_destroy();
    }

    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void {
        unset($_SESSION[$key]);
    }
}
