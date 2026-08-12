<?php

namespace API;

/**
 * Admin endpoints - /api/admin.<method>.json
 *
 * Every method checks the role itself, the router does not protect /api.
 */
class admin {

    public static function settings(array $data): array {
        if (!\MyUser::isAdmin()) \API::fail("Administrators only", 403);
        return ["settings" => \Config::all(), "defaults" => \Config::DEFAULTS];
    }

    public static function setsettings(array $data): array {
        if (!\MyUser::isAdmin()) \API::fail("Administrators only", 403);
        $saved = [];
        foreach ($data as $key => $value) {
            if ($key === "csrf_token" || !array_key_exists($key, \Config::DEFAULTS)) continue;
            \Config::set($key, is_array($value) ? json_encode($value) : (string)$value);
            $saved[] = $key;
        }
        \Audit::log("admin.settings", "", ["keys" => $saved]);
        \WebCache::flush();
        return ["saved" => $saved];
    }

    public static function queue(array $data): array {
        if (!\MyUser::isModerator()) \API::fail("Moderators only", 403);
        return \Moderation::queue();
    }

    public static function suspenduser(array $data): array {
        if (!\MyUser::isModerator()) \API::fail("Moderators only", 403);
        \Moderation::suspend(
            \API::need($data, "id", "int"),
            \API::optional($data, "days", 7, "int"),
            (string)\API::optional($data, "reason", ""),
            \MyUser::id()
        );
        return ["suspended" => true];
    }

    public static function unsuspenduser(array $data): array {
        if (!\MyUser::isModerator()) \API::fail("Moderators only", 403);
        \Moderation::unsuspend(\API::need($data, "id", "int"), \MyUser::id());
        return ["suspended" => false];
    }

    public static function setrole(array $data): array {
        if (!\MyUser::isAdmin()) \API::fail("Administrators only", 403);
        \Moderation::setRole(\API::need($data, "id", "int"), \API::need($data, "role"), \MyUser::id());
        return ["saved" => true];
    }

    public static function recountkarma(array $data): array {
        if (!\MyUser::isAdmin()) \API::fail("Administrators only", 403);
        $karma = \Karma::recalculate(\API::need($data, "id", "int"));
        return ["karma" => $karma];
    }

    public static function statistics(array $data): array {
        if (!\MyUser::isAdmin()) \API::fail("Administrators only", 403);
        return [
            "overview" => \Statistics::overview(),
            "timeline" => \Statistics::timeline(\API::optional($data, "days", 30, "int")),
            "top"      => \Statistics::topUsers(10),
            "audit"    => \Audit::recent(50),
        ];
    }
}
