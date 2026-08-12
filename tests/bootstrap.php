<?php
/**
 * PHPUnit bootstrap - boots the application without the web entry point.
 *
 * Database backed tests are skipped automatically when MYSQL_HOST is not set,
 * so a plain "composer test" also works on a laptop without a database.
 */

$_ENV["pgmstart"] = microtime(true);
define("askbot_entrypoint", true);
$_ENV["basepath"] = __DIR__ . "/../html/app";
$_ENV["webroot"]  = __DIR__ . "/../html";
$_ENV["STAGE"]    = "development";
$_SESSION = [];

mb_internal_encoding("UTF-8");
date_default_timezone_set("UTC");

spl_autoload_register(function (string $class): void {
    $path = $_ENV["basepath"] . "/code/classes/" . str_replace("\\", "/", $class) . ".php";
    if (is_file($path)) { require_once $path; return; }
    $dir = dirname($path); $base = basename($path);
    if (is_dir($dir)) {
        foreach (scandir($dir) as $entry) {
            if (strcasecmp($entry, $base) === 0) { require_once $dir . "/" . $entry; return; }
        }
    }
});

function html(?string $t): string { return htmlspecialchars((string)$t, ENT_QUOTES, "UTF-8"); }
function htmlattr(?string $t): string { return html($t); }
function url(string $path = "/"): string { return Config::baseUrl() . "/" . ltrim($path, "/"); }
function asset(string $path): string { return url("skins/default/" . ltrim($path, "/")); }

class_exists("i18n");
i18n::setLanguage("en");

if (getenv("MYSQL_HOST")) {
    SQL::init(0, sprintf(
        "mysql://%s:%s@%s:%d/%s/",
        rawurlencode((string)getenv("MYSQL_USER")),
        rawurlencode((string)getenv("MYSQL_PASSWORD")),
        (string)getenv("MYSQL_HOST"),
        (int)(getenv("MYSQL_PORT") ?: 3306),
        (string)getenv("MYSQL_DB")
    ));
}

/** True when the tests may talk to a database. */
function hasDatabase(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    if (!getenv("MYSQL_HOST")) return $ok = false;
    try { (new SQL(0))->cmdvalue("SELECT 1"); return $ok = true; }
    catch (\Throwable $e) { return $ok = false; }
}
