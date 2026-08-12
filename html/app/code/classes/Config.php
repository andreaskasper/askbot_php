<?php

/**
 * Config - site settings.
 *
 * Values live in the `config` table so they can be changed in the admin area
 * without touching a file. Environment variables win over database values,
 * which win over the hard coded defaults below.
 */
class Config {

    private static array $_cache = [];
    private static bool $_loaded = false;

    /** Fallbacks, also used to render the admin settings form. */
    public const DEFAULTS = [
        "site_title"                => "Askbot",
        "site_tagline"              => "Ask questions. Get answers. Share knowledge.",
        "site_description"          => "A community driven question and answer site.",
        "site_language"             => "en",
        "site_theme"                => "auto",
        "questions_per_page"        => 30,
        "answers_per_page"          => 30,
        "allow_anonymous_read"      => 1,
        "registration_open"         => 1,
        "require_email_verification"=> 1,
        "min_title_length"          => 15,
        "min_question_length"       => 20,
        "min_answer_length"         => 20,
        "max_tags_per_question"     => 5,
        "min_tags_per_question"     => 1,
        "karma_new_user"            => 1,
        "karma_question_upvote"     => 5,
        "karma_question_downvote"   => -2,
        "karma_answer_upvote"       => 10,
        "karma_answer_downvote"     => -2,
        "karma_downvote_cost"       => -1,
        "karma_answer_accepted"     => 15,
        "karma_accept_answer"       => 2,
        "karma_daily_cap"           => 200,
        "threshold_comment"         => 1,
        "threshold_vote_up"         => 15,
        "threshold_vote_down"       => 125,
        "threshold_flag"            => 15,
        "threshold_edit_wiki"       => 100,
        "threshold_close_vote"      => 500,
        "threshold_edit_others"     => 2000,
        "threshold_tag_wiki"        => 1500,
        "threshold_delete_vote"     => 3000,
        "close_votes_needed"        => 3,
        "flags_needed_autohide"     => 5,
        "feed_item_count"           => 30,
    ];

    public static function load(): void {
        if (self::$_loaded) return;
        self::$_loaded = true;
        try {
            $db = new SQL(0);
            foreach ($db->cmdrows('SELECT key_name, value_text FROM config') as $row) {
                self::$_cache[$row["key_name"]] = $row["value_text"];
            }
        } catch (\Throwable $e) {
            // A missing config table means "not installed yet" - defaults are fine.
        }
    }

    public static function get(string $key, $default = null) {
        self::load();
        if (array_key_exists($key, self::$_cache)) return self::$_cache[$key];
        if (array_key_exists($key, self::DEFAULTS)) return self::DEFAULTS[$key];
        return $default;
    }

    public static function int(string $key, int $default = 0): int {
        $v = self::get($key, $default);
        return (int)$v;
    }

    public static function bool(string $key, bool $default = false): bool {
        $v = self::get($key, $default ? 1 : 0);
        return in_array((string)$v, ["1", "true", "yes", "on"], true);
    }

    public static function set(string $key, $value): void {
        self::load();
        $db = new SQL(0);
        $db->CreateUpdate("config", ["key_name" => $key, "value_text" => (string)$value], ["value_text"]);
        self::$_cache[$key] = (string)$value;
    }

    public static function all(): array {
        self::load();
        return array_merge(self::DEFAULTS, self::$_cache);
    }

    /** Environment variable with a fallback, works with getenv() and $_ENV. */
    public static function env(string $key, $default = null) {
        $v = getenv($key);
        if ($v === false || $v === "") $v = $_ENV[$key] ?? null;
        if ($v === null || $v === "") return $default;
        return $v;
    }

    /** Absolute base URL of this installation, never with a trailing slash. */
    public static function baseUrl(): string {
        $url = self::env("BASE_URL");
        if ($url === null) {
            $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
            if (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https") $scheme = "https";
            $host = $_SERVER["HTTP_HOST"] ?? "localhost";
            $url = $scheme . "://" . $host . ($_ENV["basepath_url"] ?? "");
        }
        return rtrim((string)$url, "/");
    }
}
