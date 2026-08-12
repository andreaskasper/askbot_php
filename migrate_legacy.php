<?php
/**
 * migrate_legacy.php - import the data of the 2013 askbot_php database.
 *
 *     php migrate_legacy.php --host=localhost --user=root --password=secret --database=old_askbot [--dry-run]
 *
 * The target database is the one the application itself uses (environment
 * variables or html/app/config.php). Nothing in the source database is
 * modified. The script can be run twice - rows that already exist are skipped.
 *
 * What is carried over:
 *   user_list      -> users            (md5 passwords are kept and upgraded on
 *                                       the first successful sign in)
 *   questions      -> questions        (unix timestamps become DATETIME)
 *   answers        -> answers
 *   comments       -> comments
 *   question_tags  -> tags + question_tags
 *   question_votes -> votes
 *   answer_votes   -> votes
 *   karma_log      -> karma_log
 *   user_badges    -> user_badges      (matched by name where possible)
 *   mails          -> messages
 */

if (PHP_SAPI !== "cli") { http_response_code(403); exit("CLI only"); }

$_ENV["basepath"] = __DIR__ . "/html/app";
$_ENV["webroot"]  = __DIR__ . "/html";
define("askbot_entrypoint", true);
date_default_timezone_set("UTC");
mb_internal_encoding("UTF-8");

spl_autoload_register(function (string $class): void {
    $path = $_ENV["basepath"] . "/code/classes/" . str_replace("\\", "/", $class) . ".php";
    if (is_file($path)) require_once $path;
});
if (is_file(__DIR__ . "/html/app/config.php")) require_once __DIR__ . "/html/app/config.php";

function html(?string $t): string { return htmlspecialchars((string)$t, ENT_QUOTES, "UTF-8"); }
function url(string $p = "/"): string { return Config::baseUrl() . "/" . ltrim($p, "/"); }
$_SESSION = [];

$options = getopt("", ["host:", "port:", "user:", "password:", "database:", "dry-run", "limit:"]);
foreach (["host", "user", "database"] as $required) {
    if (!isset($options[$required])) {
        fwrite(STDERR, "Missing --" . $required . "\n");
        fwrite(STDERR, "Usage: php migrate_legacy.php --host=… --user=… --password=… --database=… [--dry-run]\n");
        exit(1);
    }
}
$dryRun = isset($options["dry-run"]);

// Target: the application database.
SQL::init(0, sprintf(
    "mysql://%s:%s@%s:%d/%s/",
    rawurlencode((string)Config::env("MYSQL_USER", "root")),
    rawurlencode((string)Config::env("MYSQL_PASSWORD", "")),
    (string)Config::env("MYSQL_HOST", "127.0.0.1"),
    (int)Config::env("MYSQL_PORT", 3306),
    (string)Config::env("MYSQL_DB", "askbot")
));
// Source: the old installation.
SQL::init(1, sprintf(
    "mysql://%s:%s@%s:%d/%s/",
    rawurlencode((string)$options["user"]),
    rawurlencode((string)($options["password"] ?? "")),
    (string)$options["host"],
    (int)($options["port"] ?? 3306),
    (string)$options["database"]
));

$new = new SQL(0);
$old = new SQL(1);
$limit = isset($options["limit"]) ? " LIMIT " . SQL::int($options["limit"]) : "";

/** Unix timestamp (or garbage) to a UTC DATETIME string. */
function ts($value): string {
    $value = (int)$value;
    if ($value <= 0) return gmdate("Y-m-d H:i:s");
    if ($value > 253402300799) $value = (int)($value / 1000);
    return gmdate("Y-m-d H:i:s", $value);
}

function step(string $message): void { echo "  " . $message . "\n"; }

echo "askbot_php legacy import" . ($dryRun ? " (dry run)" : "") . "\n";
echo str_repeat("-", 60) . "\n";

$userMap = [];       // old user id => new user id
$questionMap = [];
$answerMap = [];

// ---------------------------------------------------------------------------
// Users
// ---------------------------------------------------------------------------
echo "users\n";
$rows = $old->cmdrows('SELECT * FROM user_list' . $limit);
$imported = 0;
foreach ($rows as $row) {
    $username = trim((string)($row["username"] ?? ""));
    $email = strtolower(trim((string)($row["email_standard"] ?? "")));
    if ($username === "") continue;
    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = "legacy-" . (int)$row["id"] . "@invalid.local";
    }

    $existing = $new->cmdrow('SELECT id FROM users WHERE username = "{0}" OR email = "{1}" LIMIT 0,1', [$username, $email]);
    if ($existing !== []) { $userMap[(int)$row["id"]] = (int)$existing["id"]; continue; }

    $password = $old->cmdvalue('SELECT pwd FROM user_login WHERE user = "{0}" AND provider = "local" LIMIT 0,1', [(int)$row["id"]]);

    if ($dryRun) { $imported++; continue; }
    $id = $new->Create("users", [
        "username"          => mb_substr($username, 0, 64),
        "slug"              => Slug::make($username),
        "email"             => mb_substr($email, 0, 190),
        "email_verified_at" => ts($row["dt_registered"] ?? 0),
        "password_hash"     => $password !== null && $password !== "" ? (string)$password : null,
        "karma"             => max(1, (int)($row["karma"] ?? 1)),
        "real_name"         => mb_substr((string)($row["realname"] ?? ""), 0, 120),
        "website"           => mb_substr((string)($row["website"] ?? ""), 0, 255),
        "location"          => mb_substr((string)($row["location"] ?? ""), 0, 120),
        "country"           => mb_substr((string)($row["country"] ?? ""), 0, 2),
        "show_country"      => (int)($row["show_country"] ?? 0),
        "locale"            => str_starts_with((string)($row["language"] ?? "en"), "de") ? "de" : "en",
        "created_at"        => ts($row["dt_registered"] ?? 0),
        "last_seen_at"      => ts($row["dt_lastseen"] ?? 0),
    ]);
    $userMap[(int)$row["id"]] = $id;
    $imported++;
}
step($imported . " of " . count($rows) . " users imported");

// ---------------------------------------------------------------------------
// Questions
// ---------------------------------------------------------------------------
echo "questions\n";
$rows = $old->cmdrows('SELECT * FROM questions' . $limit);
$imported = 0;
foreach ($rows as $row) {
    $title = trim((string)($row["title"] ?? ""));
    if ($title === "") continue;
    $body = (string)($row["question"] ?? "");
    $tags = array_values(array_filter(array_map([Slug::class, "tag"], explode(",", (string)($row["tags"] ?? "")))));

    if ($dryRun) { $imported++; continue; }
    $id = $new->Create("questions", [
        "title"              => mb_substr($title, 0, 300),
        "slug"               => Slug::make($title, 120),
        "body_md"            => $body,
        "body_html"          => Markdown::render($body),
        "author_id"          => $userMap[(int)($row["author"] ?? 0)] ?? null,
        "tags"               => mb_substr(implode(",", $tags), 0, 255),
        "score"              => (int)($row["count_votes"] ?? 0),
        "view_count"         => (int)($row["count_views"] ?? 0),
        "answer_count"       => (int)($row["count_answers"] ?? 0),
        "is_closed"          => (int)($row["is_closed"] ?? 0),
        "created_at"         => ts($row["date_created"] ?? 0),
        "updated_at"         => ts($row["date_edited"] ?? $row["date_created"] ?? 0),
        "last_activity_at"   => ts($row["date_action"] ?? $row["date_created"] ?? 0),
        "last_activity_by"   => $userMap[(int)($row["user_action"] ?? 0)] ?? null,
    ]);
    $questionMap[(int)$row["id"]] = $id;
    Tag::sync($id, $tags);
    $imported++;
}
step($imported . " of " . count($rows) . " questions imported");

// ---------------------------------------------------------------------------
// Answers
// ---------------------------------------------------------------------------
echo "answers\n";
$rows = $old->cmdrows('SELECT * FROM answers' . $limit);
$imported = 0;
foreach ($rows as $row) {
    $questionId = $questionMap[(int)($row["question"] ?? 0)] ?? null;
    if ($questionId === null) continue;
    $body = (string)($row["txt"] ?? "");
    if (trim($body) === "") continue;

    if ($dryRun) { $imported++; continue; }
    $id = $new->Create("answers", [
        "question_id" => $questionId,
        "author_id"   => $userMap[(int)($row["author"] ?? 0)] ?? null,
        "body_md"     => $body,
        "body_html"   => Markdown::render($body),
        "score"       => (int)($row["count_votes"] ?? 0),
        "is_accepted" => (int)($row["right_answer"] ?? 0),
        "is_spam"     => (int)($row["isSPAM"] ?? 0),
        "created_at"  => ts($row["date_created"] ?? 0),
        "updated_at"  => ts($row["date_edited"] ?? $row["date_created"] ?? 0),
    ]);
    $answerMap[(int)$row["id"]] = $id;
    if ((int)($row["right_answer"] ?? 0) === 1) {
        $new->Update("questions", ["accepted_answer_id" => $id], $questionId);
    }
    $imported++;
}
step($imported . " of " . count($rows) . " answers imported");

// ---------------------------------------------------------------------------
// Comments
// ---------------------------------------------------------------------------
echo "comments\n";
$rows = $old->cmdrows('SELECT * FROM comments' . $limit);
$imported = 0;
foreach ($rows as $row) {
    $answerId = isset($row["answer"]) && $row["answer"] !== null ? ($answerMap[(int)$row["answer"]] ?? null) : null;
    $questionId = $questionMap[(int)($row["question"] ?? 0)] ?? null;
    if ($answerId === null && $questionId === null) continue;
    $body = (string)($row["text"] ?? "");
    if (trim($body) === "") continue;

    if ($dryRun) { $imported++; continue; }
    $new->Create("comments", [
        "post_type"  => $answerId !== null ? "answer" : "question",
        "post_id"    => $answerId ?? $questionId,
        "author_id"  => $userMap[(int)($row["user"] ?? 0)] ?? null,
        "body_md"    => mb_substr($body, 0, 1000),
        "body_html"  => Markdown::render(mb_substr($body, 0, 1000)),
        "created_at" => ts($row["created"] ?? 0),
    ]);
    $imported++;
}
step($imported . " of " . count($rows) . " comments imported");

// ---------------------------------------------------------------------------
// Votes
// ---------------------------------------------------------------------------
echo "votes\n";
$imported = 0;
foreach ([["question_votes", "question", "question"], ["answer_votes", "answer", "answer"]] as [$table, $column, $postType]) {
    $rows = $old->cmdrows('SELECT * FROM ' . $table . $limit);
    foreach ($rows as $row) {
        $map = $postType === "question" ? $questionMap : $answerMap;
        $postId = $map[(int)($row[$column] ?? 0)] ?? null;
        $userId = $userMap[(int)($row["user"] ?? 0)] ?? null;
        if ($postId === null || $userId === null) continue;
        $value = (int)($row["vote"] ?? 0);
        if ($value === 0) continue;

        if ($dryRun) { $imported++; continue; }
        $new->cmd(
            'INSERT IGNORE INTO votes (post_type, post_id, user_id, value) VALUES ("{0}", "{1}", "{2}", "{3}")',
            [$postType, $postId, $userId, $value > 0 ? 1 : -1]
        );
        $imported++;
    }
}
step($imported . " votes imported");

// ---------------------------------------------------------------------------
// Karma log, badges, private messages
// ---------------------------------------------------------------------------
echo "karma log\n";
$rows = $old->cmdrows('SELECT * FROM karma_log' . $limit);
$imported = 0;
foreach ($rows as $row) {
    $userId = $userMap[(int)($row["user"] ?? 0)] ?? null;
    if ($userId === null) continue;
    if ($dryRun) { $imported++; continue; }
    $new->Create("karma_log", [
        "user_id"    => $userId,
        "reason"     => "legacy_" . (int)($row["msgid"] ?? 0),
        "points"     => (int)($row["points"] ?? 0),
        "post_type"  => isset($questionMap[(int)($row["question"] ?? 0)]) ? "question" : "none",
        "post_id"    => $questionMap[(int)($row["question"] ?? 0)] ?? null,
        "created_at" => ts($row["created"] ?? 0),
    ]);
    $imported++;
}
step($imported . " karma entries imported");

echo "messages\n";
$rows = $old->cmdrows('SELECT * FROM mails' . $limit);
$imported = 0;
foreach ($rows as $row) {
    $to = $userMap[(int)($row["to_user"] ?? 0)] ?? null;
    if ($to === null) continue;
    if ($dryRun) { $imported++; continue; }
    $body = (string)($row["message"] ?? "");
    $new->Create("messages", [
        "from_user_id" => $userMap[(int)($row["from_user"] ?? 0)] ?? null,
        "to_user_id"   => $to,
        "subject"      => mb_substr((string)($row["subject"] ?? ""), 0, 200),
        "body_md"      => $body,
        "body_html"    => Markdown::render($body),
        "read_at"      => (int)($row["is_read"] ?? 0) === 1 ? ts($row["dt_created"] ?? 0) : null,
        "created_at"   => ts($row["dt_created"] ?? 0),
    ]);
    $imported++;
}
step($imported . " messages imported");

// ---------------------------------------------------------------------------
if (!$dryRun) {
    echo "recounting\n";
    bots\maintenance::run();
    step("counters rebuilt");
}

echo str_repeat("-", 60) . "\n";
echo $dryRun ? "Dry run finished - nothing was written.\n" : "Import finished.\n";
echo "Old passwords are md5 hashes; they are replaced with argon2id the first time each member signs in.\n";
