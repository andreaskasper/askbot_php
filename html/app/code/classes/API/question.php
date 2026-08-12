<?php

namespace API;

/**
 * Question endpoints - /api/question.<method>.json
 *
 *   list      GET   filter, sort and paginate questions
 *   get       GET   one question with answers and comments
 *   similar   GET   questions that look like a title
 *   create    POST  ask a question
 *   update    POST  edit a question
 *   delete    POST  soft delete
 *   accept    POST  accept an answer
 *   close     POST  close / reopen vote
 *   favorite  POST  toggle the bookmark
 *   subscribe POST  toggle notifications
 */
class question {

    public static function list(array $data): array {
        $result = \Question::search([
            "tag"      => \API::optional($data, "tag", ""),
            "q"        => \API::optional($data, "q", ""),
            "scope"    => \API::optional($data, "scope", ""),
            "sort"     => \API::optional($data, "sort", "activity"),
            "user"     => \API::optional($data, "user", 0, "int"),
            "page"     => \API::optional($data, "page", 1, "int"),
            "per_page" => \API::optional($data, "per_page", 30, "int"),
        ]);

        $items = [];
        foreach ($result["items"] as $row) {
            $items[] = (new \Question((int)$row["id"], $row))->toArray(false);
        }
        return [
            "questions"  => $items,
            "pagination" => [
                "page"  => $result["page"],
                "pages" => $result["pages"],
                "total" => $result["total"],
                "per_page" => $result["per_page"],
            ],
        ];
    }

    public static function get(array $data): array {
        $id = \API::need($data, "id", "int");
        $question = new \Question($id);
        if (!$question->exists() || $question->deleted_at !== null) \API::fail("Question not found", 404);

        $answers = [];
        foreach (\Answer::forQuestion($id, \API::optional($data, "sort", "votes")) as $row) {
            $answer = (new \Answer((int)$row["id"], $row))->toArray();
            $answer["comments"] = array_map([\Comment::class, "toArray"], \Comment::forPost("answer", (int)$row["id"]));
            $answers[] = $answer;
        }

        $out = $question->toArray();
        $out["comments"] = array_map([\Comment::class, "toArray"], \Comment::forPost("question", $id));
        $out["answers"] = $answers;
        $out["my_votes"] = [
            "question" => \Vote::myVotes("question", [$id]),
            "answer"   => \Vote::myVotes("answer", array_column($answers, "id")),
        ];
        $out["is_favorite"] = self::isFavorite($id);
        $out["is_subscribed"] = \Subscription::has(\MyUser::id(), "question", $id);
        return $out;
    }

    public static function similar(array $data): array {
        $title = \API::need($data, "title");
        return ["questions" => \Question::similar($title, 5, \API::optional($data, "exclude", 0, "int"))];
    }

    public static function create(array $data): array {
        $question = \Question::create(
            \API::need($data, "title"),
            \API::need($data, "body"),
            \API::need($data, "tags", "array"),
            \MyUser::id()
        );
        \Notification::forNewQuestion($question->id(), \MyUser::id(), $question->tagList());
        return ["question" => $question->toArray(false), "url" => $question->url()];
    }

    public static function update(array $data): array {
        $id = \API::need($data, "id", "int");
        $question = new \Question($id);
        if (!$question->exists()) \API::fail("Question not found", 404);
        if (!\MyUser::can("edit", $question->row())) \API::fail("You are not allowed to edit this question", 403);

        $question->update(
            \API::need($data, "title"),
            \API::need($data, "body"),
            \API::need($data, "tags", "array"),
            \MyUser::id(),
            \API::optional($data, "comment", "")
        );
        return ["question" => $question->toArray(false), "url" => $question->url()];
    }

    public static function delete(array $data): array {
        $id = \API::need($data, "id", "int");
        $question = new \Question($id);
        if (!$question->exists()) \API::fail("Question not found", 404);
        if (!\MyUser::can("delete", $question->row())) \API::fail("You are not allowed to delete this question", 403);
        $question->softDelete(\MyUser::id());
        return ["deleted" => true];
    }

    public static function accept(array $data): array {
        $id = \API::need($data, "id", "int");
        $answerId = \API::need($data, "answer_id", "int");
        $question = new \Question($id);
        if (!$question->exists()) \API::fail("Question not found", 404);
        $accepted = $question->accept($answerId, \MyUser::id());
        return ["accepted" => $accepted, "answer_id" => $answerId];
    }

    public static function close(array $data): array {
        $id = \API::need($data, "id", "int");
        $action = \API::optional($data, "action", "close");
        return \Moderation::vote(
            $id,
            \MyUser::id(),
            $action,
            \API::optional($data, "reason", "unclear"),
            \API::optional($data, "duplicate_of", null, "int")
        );
    }

    public static function favorite(array $data): array {
        $id = \API::need($data, "id", "int");
        $db = new \SQL(0);
        if (self::isFavorite($id)) {
            $db->cmd('DELETE FROM favorites WHERE user_id = "{0}" AND question_id = "{1}"', [\MyUser::id(), $id]);
            $db->cmd('UPDATE questions SET favorite_count = GREATEST(0, favorite_count - 1) WHERE id = "{0}"', [$id]);
            $state = false;
        } else {
            $db->cmd('INSERT IGNORE INTO favorites (user_id, question_id) VALUES ("{0}", "{1}")', [\MyUser::id(), $id]);
            $db->cmd('UPDATE questions SET favorite_count = favorite_count + 1 WHERE id = "{0}"', [$id]);
            $state = true;
        }
        $count = $db->cmdint('SELECT favorite_count FROM questions WHERE id = "{0}"', [$id]);
        return ["is_favorite" => $state, "favorite_count" => $count];
    }

    public static function subscribe(array $data): array {
        $id = \API::need($data, "id", "int");
        return ["subscribed" => \Subscription::toggle(\MyUser::id(), "question", $id)];
    }

    private static function isFavorite(int $questionId): bool {
        if (\MyUser::id() === 0) return false;
        $db = new \SQL(0);
        return $db->cmdvalue('SELECT question_id FROM favorites WHERE user_id = "{0}" AND question_id = "{1}" LIMIT 0,1', [\MyUser::id(), $questionId]) !== null;
    }
}
