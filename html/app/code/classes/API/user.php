<?php

namespace API;

/**
 * User endpoints - /api/user.<method>.json
 */
class user {

    public static function list(array $data): array {
        $db = new \SQL(0);
        $page = max(1, \API::optional($data, "page", 1, "int"));
        $perPage = min(100, max(10, \API::optional($data, "per_page", 36, "int")));
        $offset = ($page - 1) * $perPage;

        $where = "deleted_at IS NULL";
        $values = [];
        $query = trim((string)\API::optional($data, "q", ""));
        if ($query !== "") {
            $where .= ' AND username LIKE "%{0}%"';
            $values[] = $query;
        }
        $order = match ((string)\API::optional($data, "sort", "karma")) {
            "newest" => "created_at DESC",
            "name"   => "username ASC",
            "active" => "last_seen_at DESC",
            default  => "karma DESC",
        };

        $total = $db->cmdint('SELECT COUNT(*) FROM users WHERE ' . $where, $values);
        $rows = $db->cmdrows(
            'SELECT id, username, slug, karma, country, show_country, question_count, answer_count, created_at, last_seen_at
             FROM users WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . \SQL::int($offset) . ',' . \SQL::int($perPage),
            $values
        );
        $items = [];
        foreach ($rows as $row) $items[] = (new \User((int)$row["id"], $row))->toArray();
        return ["users" => $items, "pagination" => ["page" => $page, "pages" => (int)ceil($total / $perPage), "total" => $total]];
    }

    public static function get(array $data): array {
        $user = new \User(\API::need($data, "id", "int"));
        if (!$user->exists()) \API::fail("User not found", 404);
        return ["user" => $user->toArray(\MyUser::id() === $user->id() || \MyUser::isModerator())];
    }

    public static function me(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Not signed in", 401);
        $user = \MyUser::user();
        return [
            "user" => $user->toArray(true),
            "can"  => [
                "comment"   => \MyUser::can("comment"),
                "vote_up"   => \MyUser::can("vote_up"),
                "vote_down" => \MyUser::can("vote_down"),
                "flag"      => \MyUser::can("flag"),
                "close"     => \MyUser::can("close"),
                "moderate"  => \MyUser::isModerator(),
            ],
            "unread" => \Notification::unreadCount(\MyUser::id()),
            "csrf_token" => \Csrf::token(),
        ];
    }

    public static function activity(array $data): array {
        $id = \API::need($data, "id", "int");
        $db = new \SQL(0);
        return [
            "questions" => $db->cmdrows('SELECT id, title, slug, score, answer_count, created_at FROM questions WHERE author_id = "{0}" AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 30', [$id]),
            "answers"   => $db->cmdrows(
                'SELECT a.id, a.score, a.is_accepted, a.created_at, q.id AS question_id, q.title, q.slug
                 FROM answers a JOIN questions q ON q.id = a.question_id
                 WHERE a.author_id = "{0}" AND a.deleted_at IS NULL ORDER BY a.created_at DESC LIMIT 30', [$id]
            ),
            "badges"    => \Badge::forUser($id),
            "karma"     => \Karma::history($id, 30),
        ];
    }

    public static function updateprofile(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        $id = \API::optional($data, "id", \MyUser::id(), "int");
        if ($id !== \MyUser::id() && !\MyUser::isModerator()) \API::fail("You may only edit your own profile", 403);

        $user = new \User($id);
        if (!$user->exists()) \API::fail("User not found", 404);

        $bio = (string)\API::optional($data, "bio", "");
        $fields = [
            "real_name"    => mb_substr((string)\API::optional($data, "real_name", ""), 0, 120),
            "website"      => mb_substr((string)\API::optional($data, "website", ""), 0, 255),
            "location"     => mb_substr((string)\API::optional($data, "location", ""), 0, 120),
            "country"      => mb_substr((string)\API::optional($data, "country", ""), 0, 2),
            "show_country" => \API::optional($data, "show_country", false, "bool") ? 1 : 0,
            "bio_md"       => $bio,
            "bio_html"     => \Markdown::render($bio),
            "locale"       => in_array((string)\API::optional($data, "locale", "en"), array_keys(\i18n::LANGUAGES), true) ? (string)\API::optional($data, "locale", "en") : "en",
            "email_digest" => in_array((string)\API::optional($data, "email_digest", "daily"), ["off", "daily", "weekly"], true) ? (string)\API::optional($data, "email_digest", "daily") : "daily",
            "email_on_answer"  => \API::optional($data, "email_on_answer", true, "bool") ? 1 : 0,
            "email_on_comment" => \API::optional($data, "email_on_comment", true, "bool") ? 1 : 0,
        ];
        if ($fields["website"] !== "" && !filter_var($fields["website"], FILTER_VALIDATE_URL)) {
            \API::fail("Please enter a valid website address", 422);
        }
        $user->save($fields);
        \Badge::checkUserBadges($id);
        return ["user" => $user->toArray(true)];
    }

    public static function setpassword(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        $user = \MyUser::user();
        $current = (string)\API::optional($data, "current_password", "");
        if ($user->password_hash !== null && !$user->verifyPassword($current)) {
            \API::fail("The current password is not correct", 403);
        }
        $new = \API::need($data, "password");
        if ($new !== \API::need($data, "password_repeat")) \API::fail("The two passwords do not match", 422);
        \Auth::checkPasswordStrength($new);
        $user->setPassword($new);
        \Audit::log("password.changed", "user:" . $user->id());
        return ["saved" => true];
    }

    public static function createapikey(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        $token = \Auth::createApiKey(\MyUser::id(), (string)\API::optional($data, "label", "API key"));
        return ["api_key" => $token, "note" => "Store it now - it is not shown again."];
    }

    public static function sendmessage(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        $id = \Message::send(\MyUser::id(), \API::need($data, "to", "int"), \API::need($data, "subject"), \API::need($data, "body"));
        return ["message_id" => $id];
    }
}
