<?php

namespace bots;

/**
 * cleanup - throw away data that has served its purpose.
 *
 * Schedule: daily.
 */
class cleanup {

    public static function run(array $data = []): string {
        $db = new \SQL(0);
        $report = [];

        $report[] = \RateLimiter::purge(86400) . " rate limit rows";

        $db->cmd('DELETE FROM user_tokens WHERE expires_at IS NOT NULL AND expires_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)');
        $report[] = $db->affected() . " expired tokens";

        $db->cmd('DELETE FROM question_views WHERE view_date < DATE_SUB(UTC_DATE(), INTERVAL 90 DAY)');
        $report[] = $db->affected() . " view records";

        $db->cmd('DELETE FROM notifications WHERE read_at IS NOT NULL AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 180 DAY)');
        $report[] = $db->affected() . " old notifications";

        $db->cmd('DELETE FROM mail_queue WHERE status = "sent" AND sent_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)');
        $report[] = $db->affected() . " sent mails";

        $db->cmd('DELETE FROM audit_log WHERE created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 365 DAY)');
        $report[] = $db->affected() . " audit entries";

        // Unverified accounts that never became real.
        $db->cmd('DELETE FROM users WHERE email_verified_at IS NULL AND karma <= 1 AND question_count = 0 AND answer_count = 0 AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)');
        $report[] = $db->affected() . " stale registrations";

        \WebCache::flush();
        return implode(", ", $report);
    }
}
