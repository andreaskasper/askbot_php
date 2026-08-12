<?php

namespace bots;

/**
 * notifications - turn unread notifications into mail.
 *
 * Honours the per user digest setting, never mails the same notification
 * twice and skips anything the user has already read on the site.
 *
 * Schedule: every 5 minutes.
 */
class notifications {

    public static function run(array $data = []): string {
        $db = new \SQL(0);
        $queued = 0;

        $rows = $db->cmdrows(
            'SELECT n.user_id, COUNT(*) AS items, MIN(n.created_at) AS oldest
             FROM notifications n JOIN users u ON u.id = n.user_id
             WHERE n.mailed_at IS NULL AND n.read_at IS NULL AND u.email_digest <> "off"
               AND u.email_verified_at IS NOT NULL AND u.deleted_at IS NULL
             GROUP BY n.user_id HAVING oldest < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 15 MINUTE) LIMIT 200'
        );

        foreach ($rows as $row) {
            $userId = (int)$row["user_id"];
            $user = new \User($userId);
            if (!$user->exists()) continue;

            $interval = (string)$user->email_digest === "weekly" ? 7 * 86400 : 86400;
            $lastMail = $db->cmdvalue('SELECT MAX(mailed_at) FROM notifications WHERE user_id = "{0}"', [$userId]);
            if ($lastMail !== null && strtotime((string)$lastMail . " UTC") > time() - $interval) continue;

            $items = $db->cmdrows(
                'SELECT * FROM notifications WHERE user_id = "{0}" AND mailed_at IS NULL AND read_at IS NULL ORDER BY id DESC LIMIT 25',
                [$userId]
            );
            if ($items === []) continue;

            \Mailer::queueTemplate(
                (string)$user->email,
                $user->displayName(),
                sprintf(\__("%d new notifications on %s"), count($items), (string)\Config::get("site_title", "Askbot")),
                "digest",
                ["user" => $user, "items" => $items]
            );
            $db->cmd('UPDATE notifications SET mailed_at = UTC_TIMESTAMP() WHERE user_id = "{0}" AND mailed_at IS NULL AND read_at IS NULL', [$userId]);
            $queued++;
        }
        return "queued " . $queued . " digest mails";
    }
}
