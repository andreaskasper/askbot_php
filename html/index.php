<?php
/**
 * askbot_php - single entry point.
 *
 * Everything below html/app is application code and must not be reachable over
 * HTTP; see html/.htaccess and Dockerfiles/web/apache-vhost.conf.
 */

$_ENV["pgmstart"] = microtime(true);
define("askbot_entrypoint", true);

$_ENV["basepath"] = __DIR__ . "/app";
$_ENV["webroot"]  = __DIR__;
$_ENV["STAGE"]    = getenv("STAGE") ?: ($_ENV["STAGE"] ?? "production");

mb_internal_encoding("UTF-8");
date_default_timezone_set("UTC");

// ---------------------------------------------------------------------------
// Autoloading: class "Question" -> app/code/classes/Question.php
//              class "API\question" -> app/code/classes/API/question.php
// ---------------------------------------------------------------------------
spl_autoload_register(function (string $class): void {
    $path = $_ENV["basepath"] . "/code/classes/" . str_replace("\\", "/", $class) . ".php";
    if (is_file($path)) { require_once $path; return; }
    // case insensitive fallback for API/bot namespaces written in lower case
    $dir  = dirname($path);
    $base = basename($path);
    if (is_dir($dir)) {
        foreach (scandir($dir) as $entry) {
            if (strcasecmp($entry, $base) === 0) { require_once $dir . "/" . $entry; return; }
        }
    }
});

// Optional composer packages (only needed for the dev tooling).
if (is_file(dirname(__DIR__) . "/vendor/autoload.php")) {
    require_once dirname(__DIR__) . "/vendor/autoload.php";
}

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------
$isDev = ($_ENV["STAGE"] === "development");
ini_set("display_errors", $isDev ? "1" : "0");
error_reporting($isDev ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

set_exception_handler(function (\Throwable $e) use ($isDev) {
    error_log("[askbot] " . get_class($e) . ": " . $e->getMessage() . " @ " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    if ($isDev) {
        echo "<h1>500 - " . htmlspecialchars(get_class($e), ENT_QUOTES, "UTF-8") . "</h1>";
        echo "<p>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8") . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, "UTF-8") . "</pre>";
    } else {
        PageEngine::html("page_error", ["code" => 500, "message" => "Internal server error"]);
    }
    exit(1);
});

// ---------------------------------------------------------------------------
// Configuration and database
// ---------------------------------------------------------------------------
if (is_file(__DIR__ . "/app/config.php")) {
    // Written by the installer, or created by hand on shared hosting.
    require_once __DIR__ . "/app/config.php";
}

$dbHost = Config::env("MYSQL_HOST");
$dbName = Config::env("MYSQL_DB");
if ($dbHost === null || $dbName === null) {
    require __DIR__ . "/app/code/install.php";
    exit;
}

SQL::init(0, sprintf(
    "mysql://%s:%s@%s:%d/%s/",
    rawurlencode((string)Config::env("MYSQL_USER", "root")),
    rawurlencode((string)Config::env("MYSQL_PASSWORD", "")),
    $dbHost,
    (int)Config::env("MYSQL_PORT", 3306),
    $dbName
));

// ---------------------------------------------------------------------------
// Session, user, language
// ---------------------------------------------------------------------------
Session::start();
Config::load();
MyUser::load();
i18n::detect();
i18n::init("core");

// ---------------------------------------------------------------------------
// Output helpers used by every template
// ---------------------------------------------------------------------------

/** Escape text for HTML output. */
function html(?string $txt): string {
    return htmlspecialchars((string)$txt, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/** Escape a value for an HTML attribute. */
function htmlattr(?string $txt): string {
    return htmlspecialchars((string)$txt, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/** Absolute URL for a path inside this installation. */
function url(string $path = "/"): string {
    if (str_starts_with($path, "http://") || str_starts_with($path, "https://")) return $path;
    return Config::baseUrl() . "/" . ltrim($path, "/");
}

/** Absolute URL for an asset below html/skins/. */
function asset(string $path): string {
    return url("skins/default/" . ltrim($path, "/")) . "?v=" . Askbot::ASSET_VERSION;
}

/** Dump a value while developing. */
function print_pre($value): void {
    echo "<pre>" . html(print_r($value, true)) . "</pre>";
}

\web\Routing::start();
