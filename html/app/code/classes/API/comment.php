<?php

namespace API;

/**
 * Comment endpoints - /api/comment.<method>.json
 */
class comment {

    public static function list(array $data): array {
        $postType = \API::need($data, "post_type");
        $postId = \API::need($data, "post_id", "int");
        return ["comments" => array_map([\Comment::class, "toArray"], \Comment::forPost($postType, $postId))];
    }

    public static function create(array $data): array {
        $row = \Comment::create(
            \API::need($data, "post_type"),
            \API::need($data, "post_id", "int"),
            \API::need($data, "body"),
            \MyUser::id()
        );
        return ["comment" => \Comment::toArray($row)];
    }

    public static function delete(array $data): array {
        \Comment::delete(\API::need($data, "id", "int"), \MyUser::id());
        return ["deleted" => true];
    }
}
