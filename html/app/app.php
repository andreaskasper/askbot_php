<?php
/**
 * app.php - command line entry point.
 *
 *   php html/app/app.php bot -t badges          run one background job
 *   php html/app/app.php cron --loop --sleep=60 run all due jobs forever
 *   php html/app/app.php migrate                create or update the schema
 *   php html/app/app.php admin <email>          promote a user to admin
 *   php html/app/app.php demo                   fill an empty database with sample content
 */

if (PHP_SAPI !== "cli") { http_response_code(403); exit("CLI only"); }

$_ENV["pgmstart"] = microtime(true);
define("askbot_entrypoint", true);
$_ENV["basepath"] = __DIR__;
$_ENV["webroot"]  = dirname(__DIR__);
$_ENV["STAGE"]    = getenv("STAGE") ?: "production";

mb_internal_encoding("UTF-8");
date_default_timezone_set("UTC");
error_reporting(E_ALL);
ini_set("display_errors", "1");

spl_autoload_register(function (string $class): void {
    $path = __DIR__ . "/code/classes/" . str_replace("\\", "/", $class) . ".php";
    if (is_file($path)) { require_once $path; return; }
    $dir = dirname($path); $base = basename($path);
    if (is_dir($dir)) {
        foreach (scandir($dir) as $entry) {
            if (strcasecmp($entry, $base) === 0) { require_once $dir . "/" . $entry; return; }
        }
    }
});

if (is_file(__DIR__ . "/config.php")) require_once __DIR__ . "/config.php";
if (is_file(dirname(__DIR__, 2) . "/vendor/autoload.php")) require_once dirname(__DIR__, 2) . "/vendor/autoload.php";

$_SESSION = [];

function html(?string $t): string { return htmlspecialchars((string)$t, ENT_QUOTES, "UTF-8"); }
function htmlattr(?string $t): string { return html($t); }
function url(string $path = "/"): string { return Config::baseUrl() . "/" . ltrim($path, "/"); }
function asset(string $path): string { return url("skins/default/" . ltrim($path, "/")); }

SQL::init(0, sprintf(
    "mysql://%s:%s@%s:%d/%s/",
    rawurlencode((string)Config::env("MYSQL_USER", "root")),
    rawurlencode((string)Config::env("MYSQL_PASSWORD", "")),
    (string)Config::env("MYSQL_HOST", "127.0.0.1"),
    (int)Config::env("MYSQL_PORT", 3306),
    (string)Config::env("MYSQL_DB", "askbot")
));

// Loading the i18n class also defines the global __() helper used everywhere.
i18n::setLanguage((string)Config::env("SITE_LANGUAGE", "en"));

$argvCopy = $argv;
array_shift($argvCopy);
$command = array_shift($argvCopy) ?? "help";
$options = CLI::parse($argvCopy);

switch ($command) {

    case "bot":
        $task = $options["t"] ?? $options["task"] ?? "";
        if ($task === "") CLI::die("Usage: app.php bot -t <task>   (available: " . implode(", ", CLI::tasks()) . ")");
        do {
            CLI::runTask((string)$task);
            if (isset($options["r"]) || isset($options["loop"])) sleep((int)($options["sleep"] ?? 60));
        } while (isset($options["r"]) || isset($options["loop"]));
        break;

    case "cron":
        do {
            foreach (CLI::tasks() as $task) CLI::runTask($task);
            if (isset($options["loop"])) sleep((int)($options["sleep"] ?? 60));
        } while (isset($options["loop"]));
        break;

    case "migrate":
        CLI::migrate();
        break;

    case "admin":
        $email = $argvCopy[0] ?? "";
        if ($email === "") CLI::die("Usage: app.php admin <email>");
        $user = User::byEmail($email);
        if ($user === null) CLI::die("No account with this email address");
        $user->save(["role" => "admin", "email_verified_at" => gmdate("Y-m-d H:i:s")]);
        CLI::ok($user->displayName() . " is now an administrator");
        break;

    case "verify":
        $email = $argvCopy[0] ?? "";
        if ($email === "") CLI::die("Usage: app.php verify <email>");
        $user = User::byEmail($email);
        if ($user === null) CLI::die("No account with this email address");
        $user->save(["email_verified_at" => gmdate("Y-m-d H:i:s")]);
        CLI::ok($user->displayName() . " is verified");
        break;

    case "demo":
        CLI::demoContent();
        break;

    case "version":
        echo "askbot_php " . Askbot::VERSION . " (" . Askbot::CODENAME . ")\n";
        break;

    default:
        echo "askbot_php " . Askbot::VERSION . "\n\n";
        echo "  bot -t <task> [--loop] [--sleep=60]   run a background job\n";
        echo "  cron [--loop]                         run every job once\n";
        echo "  migrate                               create or update the database schema\n";
        echo "  admin <email>                         make an account an administrator\n";
        echo "  verify <email>                        confirm an email address by hand\n";
        echo "  demo                                  add sample content to an empty database\n";
        echo "\n  tasks: " . implode(", ", CLI::tasks()) . "\n";
        break;
}
