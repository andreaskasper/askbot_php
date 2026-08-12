<?php

namespace API;

/**
 * Flag endpoints - /api/flag.<method>.json
 */
class flag {

    public static function create(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        \Flag::raise(
            \API::need($data, "post_type"),
            \API::need($data, "post_id", "int"),
            \MyUser::id(),
            \API::need($data, "reason"),
            (string)\API::optional($data, "note", "")
        );
        return ["flagged" => true];
    }

    public static function handle(array $data): array {
        if (!\MyUser::isModerator()) \API::fail("Moderators only", 403);
        \Flag::handle(\API::need($data, "id", "int"), \API::need($data, "status"), \MyUser::id());
        return ["handled" => true, "open" => \Flag::countOpen()];
    }

    public static function reasons(array $data): array {
        return ["reasons" => \Flag::REASONS];
    }
}
