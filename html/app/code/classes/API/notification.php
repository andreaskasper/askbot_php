<?php

namespace API;

/**
 * Notification endpoints - /api/notification.<method>.json
 */
class notification {

    public static function list(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        $rows = \Notification::forUser(\MyUser::id(), \API::optional($data, "limit", 30, "int"), \API::optional($data, "unread", false, "bool"));
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                "id"         => (int)$row["id"],
                "type"       => $row["type"],
                "icon"       => \Notification::icon((string)$row["type"]),
                "title"      => $row["title"],
                "url"        => $row["url"],
                "actor"      => $row["actor_name"],
                "is_read"    => $row["read_at"] !== null,
                "created_at" => $row["created_at"],
                "ago"        => \i18n::ago((string)$row["created_at"]),
            ];
        }
        return ["notifications" => $items, "unread" => \Notification::unreadCount(\MyUser::id())];
    }

    public static function markread(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        $count = \Notification::markRead(\MyUser::id(), \API::optional($data, "id", null, "int"));
        return ["marked" => $count, "unread" => \Notification::unreadCount(\MyUser::id())];
    }
}
