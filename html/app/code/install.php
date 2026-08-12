<?php
/**
 * install.php - shown when no database is configured yet.
 *
 * Collects the connection details, writes html/app/config.php and imports the
 * schema. On a container setup this page never appears because the values come
 * from environment variables.
 */

if (!defined("askbot_entrypoint")) { http_response_code(403); exit; }

$step = $_POST["step"] ?? "form";
$error = "";
$done = false;

if ($step === "install") {
    $host = trim((string)($_POST["host"] ?? "localhost"));
    $port = (int)($_POST["port"] ?? 3306);
    $user = trim((string)($_POST["user"] ?? ""));
    $password = (string)($_POST["password"] ?? "");
    $database = trim((string)($_POST["database"] ?? ""));
    $baseUrl = rtrim(trim((string)($_POST["base_url"] ?? "")), "/");

    try {
        if ($database === "" || $user === "") throw new \RuntimeException("Database name and user are required.");

        $link = new \PDO(
            "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $database . ";charset=utf8mb4",
            $user,
            $password,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $root = dirname(__DIR__, 3);
        foreach (["database.sql", "database.seed.sql"] as $file) {
            $sql = @file_get_contents($root . "/" . $file);
            if ($sql === false) throw new \RuntimeException("Cannot read " . $file);
            $link->exec($sql);
        }

        $config = "<?php\n"
            . "// Written by the installer on " . gmdate("Y-m-d H:i:s") . " UTC.\n"
            . "// Environment variables of the same name take precedence.\n"
            . "putenv(\"MYSQL_HOST=" . addslashes($host) . "\");\n"
            . "putenv(\"MYSQL_PORT=" . $port . "\");\n"
            . "putenv(\"MYSQL_USER=" . addslashes($user) . "\");\n"
            . "putenv(\"MYSQL_PASSWORD=" . addslashes($password) . "\");\n"
            . "putenv(\"MYSQL_DB=" . addslashes($database) . "\");\n"
            . "putenv(\"BASE_URL=" . addslashes($baseUrl) . "\");\n"
            . "putenv(\"APP_SECRET=" . bin2hex(random_bytes(24)) . "\");\n";

        if (@file_put_contents(dirname(__DIR__) . "/config.php", $config) === false) {
            throw new \RuntimeException("Cannot write html/app/config.php - please create it yourself with this content:\n\n" . $config);
        }
        $done = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$defaults = [
    "host"     => $_POST["host"] ?? "localhost",
    "port"     => $_POST["port"] ?? "3306",
    "user"     => $_POST["user"] ?? "askbot",
    "database" => $_POST["database"] ?? "askbot",
    "base_url" => $_POST["base_url"] ?? ((!empty($_SERVER["HTTPS"]) ? "https" : "http") . "://" . ($_SERVER["HTTP_HOST"] ?? "localhost")),
];
$e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8");
?><!doctype html>
<html lang="en" data-bs-theme="auto">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install askbot_php</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container" style="max-width: 720px">
    <h1 class="mt-5 mb-1">Install askbot_php</h1>
    <p class="text-secondary mb-4">Three fields and a database. Nothing else is needed.</p>

    <?php if ($done) { ?>
        <div class="alert alert-success">
            <h2 class="h5">Ready.</h2>
            <p class="mb-2">The schema was imported and <code>html/app/config.php</code> was written.</p>
            <p class="mb-0">The first account you register becomes the administrator.</p>
        </div>
        <a class="btn btn-primary" href="<?= $e($_POST["base_url"] ?? "/") ?>">Open the site</a>
    <?php } else { ?>
        <?php if ($error !== "") { ?>
            <div class="alert alert-danger"><pre class="mb-0" style="white-space: pre-wrap"><?= $e($error) ?></pre></div>
        <?php } ?>
        <form method="post" class="card card-body shadow-sm">
            <input type="hidden" name="step" value="install">
            <div class="row g-3">
                <div class="col-8">
                    <label class="form-label">Database host</label>
                    <input class="form-control" name="host" value="<?= $e($defaults["host"]) ?>" required>
                </div>
                <div class="col-4">
                    <label class="form-label">Port</label>
                    <input class="form-control" name="port" value="<?= $e($defaults["port"]) ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">User</label>
                    <input class="form-control" name="user" value="<?= $e($defaults["user"]) ?>" required>
                </div>
                <div class="col-6">
                    <label class="form-label">Password</label>
                    <input class="form-control" type="password" name="password">
                </div>
                <div class="col-12">
                    <label class="form-label">Database name</label>
                    <input class="form-control" name="database" value="<?= $e($defaults["database"]) ?>" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Site address</label>
                    <input class="form-control" name="base_url" value="<?= $e($defaults["base_url"]) ?>">
                </div>
            </div>
            <button class="btn btn-primary mt-4">Install</button>
        </form>
        <p class="text-secondary small mt-3">
            Running in Docker? Set <code>MYSQL_HOST</code>, <code>MYSQL_USER</code>, <code>MYSQL_PASSWORD</code>
            and <code>MYSQL_DB</code> instead - this page disappears as soon as they are present.
        </p>
    <?php } ?>
</div>
</body>
</html>
