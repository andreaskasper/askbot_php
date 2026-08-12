<?php

namespace API;

/**
 * Badge endpoints - /api/badge.<method>.json
 */
class badge {

    public static function list(array $data): array {
        return ["badges" => \Badge::all()];
    }

    public static function get(array $data): array {
        $badge = \Badge::one(\API::need($data, "id", "int"));
        if ($badge === []) \API::fail("Badge not found", 404);
        $badge["recipients"] = \Badge::recipients((int)$badge["id"], 50);
        return ["badge" => $badge];
    }
}
