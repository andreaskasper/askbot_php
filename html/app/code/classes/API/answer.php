<?php

namespace API;

/**
 * Answer endpoints - /api/answer.<method>.json
 */
class answer {

    public static function list(array $data): array {
        $questionId = \API::need($data, "question_id", "int");
        $items = [];
        foreach (\Answer::forQuestion($questionId, \API::optional($data, "sort", "votes")) as $row) {
            $answer = (new \Answer((int)$row["id"], $row))->toArray();
            $answer["comments"] = array_map([\Comment::class, "toArray"], \Comment::forPost("answer", (int)$row["id"]));
            $items[] = $answer;
        }
        return ["answers" => $items];
    }

    public static function create(array $data): array {
        $answer = \Answer::create(
            \API::need($data, "question_id", "int"),
            \API::need($data, "body"),
            \MyUser::id()
        );
        return ["answer" => $answer->toArray(), "url" => $answer->url()];
    }

    public static function update(array $data): array {
        $id = \API::need($data, "id", "int");
        $answer = new \Answer($id);
        if (!$answer->exists()) \API::fail("Answer not found", 404);
        if (!\MyUser::can("edit", $answer->row())) \API::fail("You are not allowed to edit this answer", 403);
        $answer->update(\API::need($data, "body"), \MyUser::id(), \API::optional($data, "comment", ""));
        return ["answer" => $answer->toArray(), "url" => $answer->url()];
    }

    public static function delete(array $data): array {
        $id = \API::need($data, "id", "int");
        $answer = new \Answer($id);
        if (!$answer->exists()) \API::fail("Answer not found", 404);
        if (!\MyUser::can("delete", $answer->row())) \API::fail("You are not allowed to delete this answer", 403);
        $answer->softDelete(\MyUser::id());
        return ["deleted" => true];
    }
}
