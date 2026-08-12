<?php

/**
 * Audit - append only log of security relevant actions.
 */
class Audit {

    public static function log(string $action, string $target = "", array $data = [], ?int $userId = null): void {
        try {
            $db = new SQL(0);
            $db->Create("audit_log", [
                "user_id"   => $userId ?? (MyUser::id() ?: null),
                "action"    => mb_substr($action, 0, 48),
                "target"    => mb_substr($target, 0, 64),
                "data_json" => $data === [] ? null : json_encode($data, JSON_UNESCAPED_UNICODE),
                "ip_hash"   => Firewall::ipHash(),
            ]);
        } catch (\Throwable $e) {
            error_log("[askbot] audit failed: " . $e->getMessage());
        }
    }

    public static function recent(int $limit = 100): array {
        $db = new SQL(0);
        return $db->cmdrows('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . SQL::int($limit));
    }
}
