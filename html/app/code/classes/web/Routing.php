<?php

namespace web;

use Config;
use PageEngine;
use MyUser;
use Question;
use Firewall;

/**
 * Routing - maps a request URI onto a template.
 *
 * Cheap exact matches first, regular expressions afterwards. Every branch ends
 * in a template plus exit, so the file reads top to bottom like a site map.
 */
class Routing {

    public static string $path = "";

    public static function start(): void {
        $uri = $_SERVER["REQUEST_URI"] ?? "/";

        // Strip the installation sub directory ("/askbot/questions" -> "/questions").
        // SCRIPT_NAME is only trustworthy when it really points at index.php -
        // PHP's built in server reports the request path instead, so the base
        // URL is the fallback.
        $scriptName = (string)($_SERVER["SCRIPT_NAME"] ?? "");
        if (str_ends_with($scriptName, "/index.php")) {
            $prefix = substr($scriptName, 0, -strlen("/index.php"));
        } else {
            $prefix = rtrim((string)parse_url(Config::baseUrl(), PHP_URL_PATH), "/");
        }
        if ($prefix !== "" && $prefix !== "/" && str_starts_with($uri, $prefix . "/")) {
            $uri = substr($uri, strlen($prefix));
        }
        $path = parse_url($uri, PHP_URL_PATH);
        if ($path === false || $path === null) $path = "/";
        $path = rawurldecode($path);
        if (strlen($path) > 1) $path = rtrim($path, "/");
        if ($path === "") $path = "/";
        self::$path = $path;
        $_ENV["path"] = $path;

        Firewall::guard($path);

        // ------------------------------------------------------------------
        // API:  /api/question.get.json   or   /api/question/get/json
        // ------------------------------------------------------------------
        if (preg_match('@^/api/(?P<namespace>[a-z0-9_]+)[./](?P<method>[a-z0-9_]+)([./](?P<format>[a-z-]+))?$@i', $path, $m)) {
            \API::run($m["namespace"], $m["method"], $m["format"] ?? "json", $_REQUEST);
            exit;
        }

        // ------------------------------------------------------------------
        // Static routes
        // ------------------------------------------------------------------
        switch ($path) {
            case "/":
            case "/questions":
                PageEngine::html("page_questions"); exit;

            case "/questions/ask":
            case "/ask":
                self::requireLogin("/questions/ask");
                PageEngine::html("page_ask"); exit;

            case "/search":
                PageEngine::html("page_search"); exit;

            case "/tags":
                PageEngine::html("page_tags"); exit;

            case "/users":
                PageEngine::html("page_users"); exit;

            case "/badges":
                PageEngine::html("page_badges"); exit;

            case "/notifications":
                self::requireLogin("/notifications");
                PageEngine::html("page_notifications"); exit;

            case "/account/signin":
                PageEngine::html("page_signin"); exit;

            case "/account/signup":
                PageEngine::html("page_signup"); exit;

            case "/account/signout":
                PageEngine::html("page_signout"); exit;

            case "/account/recover":
                PageEngine::html("page_recover"); exit;

            case "/account/settings":
                self::requireLogin("/account/settings");
                PageEngine::html("page_settings"); exit;

            case "/account/2fa":
                self::requireLogin("/account/2fa");
                PageEngine::html("page_2fa"); exit;

            case "/admin":
            case "/admin/settings":
                self::requireStaff();
                PageEngine::html("admin/page_settings"); exit;

            case "/admin/users":
                self::requireStaff();
                PageEngine::html("admin/page_users"); exit;

            case "/admin/moderation":
                self::requireModerator();
                PageEngine::html("admin/page_moderation"); exit;

            case "/admin/statistics":
                self::requireStaff();
                PageEngine::html("admin/page_statistics"); exit;

            case "/admin/health":
                self::requireStaff();
                PageEngine::html("admin/page_health"); exit;

            case "/help":       PageEngine::html("page_help"); exit;
            case "/faq":        PageEngine::html("page_faq"); exit;
            case "/about":      PageEngine::html("page_about"); exit;
            case "/privacy":    PageEngine::html("page_privacy"); exit;
            case "/terms":      PageEngine::html("page_terms"); exit;

            case "/questions/rss":
            case "/feeds/questions.rss":
                PageEngine::html("feed_questions"); exit;
            case "/feeds/answers.rss":
                PageEngine::html("feed_answers"); exit;

            case "/sitemap.xml":            PageEngine::html("xml_sitemap"); exit;
            case "/robots.txt":             PageEngine::html("txt_robots"); exit;
            case "/opensearch.xml":         PageEngine::html("xml_opensearch"); exit;
            case "/manifest.webmanifest":   PageEngine::html("json_manifest"); exit;
        }

        // ------------------------------------------------------------------
        // Pattern routes
        // ------------------------------------------------------------------

        // /question/123/how-to-do-x[/edit|/revisions|/close]
        if (preg_match('@^/question/(?P<id>[0-9]+)(?:/(?P<slug>[^/]*))?(?P<tail>/[a-z/]+)?$@', $path, $m)) {
            $id = (int)$m["id"];
            switch ($m["tail"] ?? "") {
                case "/edit":
                    self::requireLogin($path);
                    PageEngine::html("page_question_edit", ["id" => $id]); exit;
                case "/revisions":
                    PageEngine::html("page_revisions", ["post_type" => "question", "id" => $id]); exit;
                case "/rss":
                    PageEngine::html("feed_question", ["id" => $id]); exit;
                default:
                    PageEngine::html("page_question", ["id" => $id, "slug" => $m["slug"] ?? ""]); exit;
            }
        }

        // /answer/456[/edit|/revisions] - answers redirect onto their question
        if (preg_match('@^/answer/(?P<id>[0-9]+)(?P<tail>/[a-z]+)?$@', $path, $m)) {
            $id = (int)$m["id"];
            switch ($m["tail"] ?? "") {
                case "/edit":
                    self::requireLogin($path);
                    PageEngine::html("page_answer_edit", ["id" => $id]); exit;
                case "/revisions":
                    PageEngine::html("page_revisions", ["post_type" => "answer", "id" => $id]); exit;
                default:
                    $questionId = (new \Answer($id))->question_id;
                    if ($questionId) PageEngine::goto(Question::permalink((int)$questionId) . "#answer-" . $id);
                    PageEngine::error(404);
            }
        }

        // /tags/php[/edit|/synonyms]
        if (preg_match('@^/tags/(?P<name>[^/]+)(?P<tail>/[a-z]+)?$@', $path, $m)) {
            $name = $m["name"];
            switch ($m["tail"] ?? "") {
                case "/edit":
                    self::requireLogin($path);
                    PageEngine::html("page_tag_edit", ["name" => $name]); exit;
                case "/synonyms":
                    PageEngine::html("page_tag_synonyms", ["name" => $name]); exit;
                default:
                    PageEngine::goto(url("questions?tag=" . rawurlencode($name)));
            }
        }

        // /users/7/andreas[/questions|/answers|/karma|/badges|/inbox|/edit]
        if (preg_match('@^/users/(?P<id>[0-9]+)(?:/(?P<slug>[^/]*))?(?P<tail>/[a-z]+)?$@', $path, $m)) {
            $id = (int)$m["id"];
            $tab = ltrim($m["tail"] ?? "", "/");
            if (in_array($tab, ["inbox", "edit"], true) && MyUser::id() !== $id && !MyUser::isModerator()) {
                PageEngine::error(403);
            }
            PageEngine::html("page_user", ["id" => $id, "tab" => $tab !== "" ? $tab : "activity"]); exit;
        }

        // /badges/3/teacher
        if (preg_match('@^/badges/(?P<id>[0-9]+)(?:/[^/]*)?$@', $path, $m)) {
            PageEngine::html("page_badge", ["id" => (int)$m["id"]]); exit;
        }

        // /account/verify/<token>  and  /account/reset/<token>
        if (preg_match('@^/account/(?P<action>verify|reset)/(?P<token>[A-Za-z0-9_-]{16,128})$@', $path, $m)) {
            PageEngine::html("page_token", ["action" => $m["action"], "token" => $m["token"]]); exit;
        }

        // /account/oauth/github[/callback]
        if (preg_match('@^/account/oauth/(?P<provider>[a-z]+)(?P<tail>/callback)?$@', $path, $m)) {
            PageEngine::html("page_oauth", ["provider" => $m["provider"], "callback" => isset($m["tail"])]); exit;
        }

        PageEngine::error(404);
    }

    /** Send anonymous visitors to the sign in page, remembering where to return. */
    private static function requireLogin(string $returnTo): void {
        if (MyUser::isLoggedIn()) return;
        $_SESSION["return_to"] = $returnTo;
        PageEngine::goto(url("account/signin"));
    }

    private static function requireStaff(): void {
        if (!MyUser::isAdmin()) PageEngine::error(403, "Administrators only");
    }

    private static function requireModerator(): void {
        if (!MyUser::isModerator()) PageEngine::error(403, "Moderators only");
    }
}
