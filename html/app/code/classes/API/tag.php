<?php

namespace API;

/**
 * Tag endpoints - /api/tag.<method>.json
 */
class tag {

    public static function list(array $data): array {
        $result = \Tag::all(
            \API::optional($data, "sort", "popular"),
            \API::optional($data, "q", ""),
            \API::optional($data, "page", 1, "int"),
            \API::optional($data, "per_page", 36, "int")
        );
        return ["tags" => $result["items"], "pagination" => ["page" => $result["page"], "pages" => $result["pages"], "total" => $result["total"]]];
    }

    public static function suggest(array $data): array {
        return ["tags" => \Tag::suggest(\API::optional($data, "q", ""), \API::optional($data, "limit", 10, "int"))];
    }

    public static function get(array $data): array {
        $row = \Tag::byName(\API::need($data, "name"));
        if ($row === []) \API::fail("Tag not found", 404);
        $row["synonyms"] = \Tag::synonyms((string)$row["name"]);
        return ["tag" => $row];
    }

    public static function savewiki(array $data): array {
        if (!\MyUser::can("tag_wiki")) \API::fail("You do not have enough karma to edit tag wikis", 403);
        \Tag::saveWiki(\API::need($data, "name"), \API::need($data, "description"), \MyUser::id());
        return ["saved" => true];
    }

    public static function addsynonym(array $data): array {
        if (!\MyUser::can("tag_wiki")) \API::fail("You do not have enough karma to manage synonyms", 403);
        \Tag::addSynonym(\API::need($data, "source"), \API::need($data, "target"), \MyUser::id());
        return ["saved" => true];
    }
}
