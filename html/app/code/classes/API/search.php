<?php

namespace API;

/**
 * Search endpoints - /api/search.<method>.json
 */
class search {

    public static function query(array $data): array {
        $result = \Search::run(\API::optional($data, "q", ""), [
            "page"     => \API::optional($data, "page", 1, "int"),
            "per_page" => \API::optional($data, "per_page", 30, "int"),
            "sort"     => \API::optional($data, "sort", "activity"),
        ]);
        $items = [];
        foreach ($result["items"] as $row) {
            $items[] = (new \Question((int)$row["id"], $row))->toArray(false);
        }
        return [
            "questions"  => $items,
            "answers"    => $result["answers"],
            "pagination" => ["page" => $result["page"], "pages" => $result["pages"], "total" => $result["total"]],
        ];
    }

    public static function suggest(array $data): array {
        return ["questions" => \Search::suggest(\API::optional($data, "q", ""))];
    }
}
