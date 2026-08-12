<?php

/**
 * Message - private messages between members.
 */
class Message {

    public static function send(int $fromUserId, int $toUserId, string $subject, string $bodyMd): int {
        if ($toUserId <= 0 || $fromUserId === $toUserId) throw new \InvalidArgumentException(__("Please pick a different recipient."));
        if (trim($subject) === "") throw new \InvalidArgumentException(__("Please enter a subject."));
        if (mb_strlen(trim($bodyMd)) < 2) throw new \InvalidArgumentException(__("Your message is empty."));
        if (!RateLimiter::check("pm:" . $fromUserId, 30, 3600)) throw new \RuntimeException(__("You are sending messages too fast."));

        $recipient = new User($toUserId);
        if (!$recipient->exists()) throw new \InvalidArgumentException(__("Unknown recipient."));

        $db = new SQL(0);
        $id = $db->Create("messages", [
            "from_user_id" => $fromUserId,
            "to_user_id"   => $toUserId,
            "subject"      => mb_substr($subject, 0, 200),
            "body_md"      => $bodyMd,
            "body_html"    => Markdown::render($bodyMd),
        ]);
        Notification::create($toUserId, "message", $fromUserId, "none", null,
            sprintf(__("New message: %s"), mb_substr($subject, 0, 120)), url("users/" . $toUserId . "/x/inbox"));
        return $id;
    }

    public static function inbox(int $userId, int $limit = 50): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT m.*, u.username AS from_name, u.slug AS from_slug FROM messages m LEFT JOIN users u ON u.id = m.from_user_id
             WHERE m.to_user_id = "{0}" AND m.deleted_by_receiver = 0 ORDER BY m.id DESC LIMIT ' . SQL::int($limit),
            [$userId]
        );
    }

    public static function sent(int $userId, int $limit = 50): array {
        $db = new SQL(0);
        return $db->cmdrows(
            'SELECT m.*, u.username AS to_name, u.slug AS to_slug FROM messages m LEFT JOIN users u ON u.id = m.to_user_id
             WHERE m.from_user_id = "{0}" AND m.deleted_by_sender = 0 ORDER BY m.id DESC LIMIT ' . SQL::int($limit),
            [$userId]
        );
    }

    public static function markRead(int $messageId, int $userId): void {
        $db = new SQL(0);
        $db->cmd('UPDATE messages SET read_at = UTC_TIMESTAMP() WHERE id = "{0}" AND to_user_id = "{1}" AND read_at IS NULL', [$messageId, $userId]);
    }

    public static function unreadCount(int $userId): int {
        $db = new SQL(0);
        return $db->cmdint('SELECT COUNT(*) FROM messages WHERE to_user_id = "{0}" AND read_at IS NULL AND deleted_by_receiver = 0', [$userId]);
    }
}
