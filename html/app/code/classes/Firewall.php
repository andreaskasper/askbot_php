<?php

/**
 * Firewall - cheap request level protection that runs before routing.
 *
 * Deliberately simple: security headers, a global rate limit per IP and a
 * crawler check the expensive pages can ask for.
 */
class Firewall {

    public static function guard(string $path): void {
        if (PHP_SAPI === "cli") return;

        if (!headers_sent()) {
            header("X-Content-Type-Options: nosniff");
            header("X-Frame-Options: SAMEORIGIN");
            header("Referrer-Policy: strict-origin-when-cross-origin");
            header("Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()");
            header_remove("X-Powered-By");
        }

        // 240 requests per minute per IP is far above human usage.
        if (!str_starts_with($path, "/skins/") && !RateLimiter::check("req:" . self::ipHash(), 240, 60)) {
            http_response_code(429);
            header("Retry-After: 60");
            echo "429 - too many requests";
            exit;
        }
    }

    /** Salted hash of the client IP - we never store the address itself. */
    public static function ipHash(?string $ip = null): string {
        $ip = $ip ?? self::ip();
        return hash("sha256", (string)Config::env("APP_SECRET", "askbot") . "|" . $ip);
    }

    public static function ip(): string {
        // Only trust proxy headers when the request came from the local network.
        $remote = $_SERVER["REMOTE_ADDR"] ?? "0.0.0.0";
        if (self::isPrivate($remote) && !empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $parts = explode(",", (string)$_SERVER["HTTP_X_FORWARDED_FOR"]);
            $candidate = trim($parts[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
        }
        return $remote;
    }

    private static function isPrivate(string $ip): bool {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    public static function isCrawler(): bool {
        $ua = strtolower($_SERVER["HTTP_USER_AGENT"] ?? "");
        if ($ua === "") return true;
        foreach (["bot", "crawler", "spider", "slurp", "facebookexternalhit", "curl/", "wget", "python-requests"] as $needle) {
            if (str_contains($ua, $needle)) return true;
        }
        return false;
    }
}
