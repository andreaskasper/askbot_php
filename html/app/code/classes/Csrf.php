<?php

/**
 * Csrf - per session token for every state changing request.
 *
 * Templates render Csrf::field(), the API checks Csrf::validate() for all
 * non GET requests that are not authenticated by an API key.
 */
class Csrf {

    public static function token(): string {
        if (empty($_SESSION["csrf"])) {
            $_SESSION["csrf"] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION["csrf"];
    }

    public static function field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES, "UTF-8") . '">';
    }

    public static function validate(?string $token = null): bool {
        if ($token === null) {
            $token = $_POST["csrf_token"] ?? $_SERVER["HTTP_X_CSRF_TOKEN"] ?? "";
        }
        if (empty($_SESSION["csrf"]) || !is_string($token) || $token === "") return false;
        return hash_equals((string)$_SESSION["csrf"], $token);
    }

    /** Validate or stop with 403 - for form posts inside templates. */
    public static function check(): void {
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") return;
        if (self::validate()) return;
        PageEngine::error(403, "Invalid or expired form token. Please try again.");
    }
}
