<?php

/**
 * RateLimiter - sliding window counter backed by the `rate_limits` table.
 *
 *     if (!RateLimiter::check("signin:" . Firewall::ipHash(), 10, 300)) { ... }
 */
class RateLimiter {

    /**
     * @param string $bucket  identifier, keep it short
     * @param int    $limit   allowed hits inside the window
     * @param int    $window  window length in seconds
     * @return bool  true when the request is allowed (and counted)
     */
    public static function check(string $bucket, int $limit = 60, int $window = 60): bool {
        try {
            $db = new SQL(0);
            $used = $db->cmdint(
                'SELECT COUNT(*) FROM rate_limits WHERE bucket = "{0}" AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL {1} SECOND)',
                [$bucket, SQL::int($window)]
            );
            if ($used >= $limit) return false;
            $db->Create("rate_limits", ["bucket" => $bucket]);
            return true;
        } catch (\Throwable $e) {
            // Never lock the site out because the limiter table is missing.
            return true;
        }
    }

    /** Remaining hits, for the X-RateLimit-Remaining header. */
    public static function remaining(string $bucket, int $limit, int $window): int {
        try {
            $db = new SQL(0);
            $used = $db->cmdint(
                'SELECT COUNT(*) FROM rate_limits WHERE bucket = "{0}" AND created_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL {1} SECOND)',
                [$bucket, SQL::int($window)]
            );
            return max(0, $limit - $used);
        } catch (\Throwable $e) {
            return $limit;
        }
    }

    public static function purge(int $olderThanSeconds = 86400): int {
        $db = new SQL(0);
        $db->cmd('DELETE FROM rate_limits WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL {0} SECOND)', [SQL::int($olderThanSeconds)]);
        return $db->affected();
    }
}
