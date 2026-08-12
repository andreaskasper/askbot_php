<?php

namespace API;

/**
 * Site endpoints - /api/site.<method>.json
 */
class site {

    public static function info(array $data): array {
        return [
            "name"        => \Config::get("site_title"),
            "tagline"     => \Config::get("site_tagline"),
            "url"         => \Config::baseUrl(),
            "version"     => \Askbot::VERSION,
            "api_version" => \API::VERSION,
            "languages"   => \i18n::LANGUAGES,
            "thresholds"  => [
                "comment"   => \Config::int("threshold_comment"),
                "vote_up"   => \Config::int("threshold_vote_up"),
                "vote_down" => \Config::int("threshold_vote_down"),
                "flag"      => \Config::int("threshold_flag"),
                "close"     => \Config::int("threshold_close_vote"),
            ],
        ];
    }

    public static function stats(array $data): array {
        return ["stats" => \Statistics::overview()];
    }

    public static function upload(array $data): array {
        if (!\MyUser::isLoggedIn()) \API::fail("Please sign in", 401);
        if (empty($_FILES["file"])) \API::fail("No file received", 422);
        if (!\RateLimiter::check("upload:" . \MyUser::id(), 30, 3600)) \API::fail("Too many uploads", 429);
        return \Upload::image($_FILES["file"], \MyUser::id());
    }

    public static function preview(array $data): array {
        return ["html" => \Markdown::render((string)\API::optional($data, "body", ""))];
    }
}
