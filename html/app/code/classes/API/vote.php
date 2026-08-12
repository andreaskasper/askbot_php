<?php

namespace API;

/**
 * Vote endpoints - /api/vote.cast.json
 */
class vote {

    public static function cast(array $data): array {
        $postType = \API::need($data, "post_type");
        $postId   = \API::need($data, "post_id", "int");
        $value    = (int)\API::need($data, "value", "int");
        return \Vote::cast($postType, $postId, $value);
    }
}
