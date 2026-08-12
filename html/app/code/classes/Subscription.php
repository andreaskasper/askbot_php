<?php

/**
 * Subscription - "notify me about this question / tag / user".
 */
class Subscription {

    public static function add(int $userId, string $targetType, int $targetId): bool {
        if ($userId <= 0 || $targetId <= 0) return false;
        $db = new SQL(0);
        $db->cmd(
            'INSERT IGNORE INTO subscriptions (user_id, target_type, target_id) VALUES ("{0}", "{1}", "{2}")',
            [$userId, $targetType, $targetId]
        );
        return true;
    }

    public static function remove(int $userId, string $targetType, int $targetId): bool {
        $db = new SQL(0);
        $db->cmd('DELETE FROM subscriptions WHERE user_id = "{0}" AND target_type = "{1}" AND target_id = "{2}"', [$userId, $targetType, $targetId]);
        return true;
    }

    public static function toggle(int $userId, string $targetType, int $targetId): bool {
        if (self::has($userId, $targetType, $targetId)) { self::remove($userId, $targetType, $targetId); return false; }
        self::add($userId, $targetType, $targetId);
        return true;
    }

    public static function has(int $userId, string $targetType, int $targetId): bool {
        if ($userId <= 0) return false;
        $db = new SQL(0);
        return $db->cmdvalue(
            'SELECT id FROM subscriptions WHERE user_id = "{0}" AND target_type = "{1}" AND target_id = "{2}" LIMIT 0,1',
            [$userId, $targetType, $targetId]
        ) !== null;
    }

    /** @return int[] user ids */
    public static function subscribers(string $targetType, int $targetId): array {
        $db = new SQL(0);
        $rows = $db->cmdrows('SELECT user_id FROM subscriptions WHERE target_type = "{0}" AND target_id = "{1}"', [$targetType, $targetId]);
        return array_map(fn($r) => (int)$r["user_id"], $rows);
    }

    public static function forUser(int $userId, string $targetType): array {
        $db = new SQL(0);
        return $db->cmdrows('SELECT * FROM subscriptions WHERE user_id = "{0}" AND target_type = "{1}"', [$userId, $targetType]);
    }
}
