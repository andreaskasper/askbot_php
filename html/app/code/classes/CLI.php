<?php

/**
 * CLI - helpers for html/app/app.php.
 */
class CLI {

    public static function parse(array $args): array {
        $out = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (preg_match('/^--([a-z0-9_-]+)(?:=(.*))?$/i', $arg, $m)) {
                $out[$m[1]] = $m[2] ?? true;
            } elseif (preg_match('/^-([a-z])$/i', $arg, $m)) {
                $next = $args[$i + 1] ?? true;
                if (is_string($next) && !str_starts_with($next, "-")) { $out[$m[1]] = $next; $i++; }
                else $out[$m[1]] = true;
            }
        }
        return $out;
    }

    /** @return string[] names of the classes in code/classes/bots/ */
    public static function tasks(): array {
        $out = [];
        foreach (glob($_ENV["basepath"] . "/code/classes/bots/*.php") ?: [] as $file) {
            $out[] = basename($file, ".php");
        }
        sort($out);
        return $out;
    }

    public static function runTask(string $task): void {
        $class = "bots\\" . preg_replace('/[^a-z0-9_]/i', "", $task);
        if (!class_exists($class)) { self::warn("Unknown task: " . $task); return; }
        $started = microtime(true);
        try {
            $result = $class::run();
            self::ok(sprintf("%-14s %s (%.2fs)", $task, is_string($result) ? $result : "done", microtime(true) - $started));
        } catch (\Throwable $e) {
            self::warn($task . " failed: " . $e->getMessage());
            error_log("[askbot][bot:" . $task . "] " . $e->getMessage());
        }
    }

    /** Import database.sql and database.seed.sql when tables are missing. */
    public static function migrate(): void {
        $db = new SQL(0);
        $root = dirname($_ENV["basepath"], 2);

        foreach (["database.sql", "database.seed.sql"] as $file) {
            $path = $root . "/" . $file;
            if (!is_file($path)) { self::warn("missing " . $file); continue; }
            $sql = (string)file_get_contents($path);
            $statements = self::splitStatements($sql);
            $applied = 0;
            foreach ($statements as $statement) {
                try { $db->cmd($statement); $applied++; }
                catch (\Throwable $e) { self::warn(substr($e->getMessage(), 0, 120)); }
            }
            self::ok($file . ": " . $applied . "/" . count($statements) . " statements applied");
        }
    }

    private static function splitStatements(string $sql): array {
        $sql = preg_replace('/^--.*$/m', "", $sql) ?? $sql;
        $out = [];
        foreach (explode(";\n", $sql) as $statement) {
            $statement = trim($statement);
            if ($statement !== "" && $statement !== ";") $out[] = rtrim($statement, ";");
        }
        return $out;
    }

    /** Sample content so a fresh installation does not look broken. */
    public static function demoContent(): void {
        $db = new SQL(0);
        if ($db->cmdint('SELECT COUNT(*) FROM questions') > 0) { self::warn("Database already has questions - skipping"); return; }

        $users = [];
        foreach ([["ada", "ada@example.com"], ["linus", "linus@example.com"], ["grace", "grace@example.com"]] as [$name, $mail]) {
            $existing = User::byUsername($name);
            $user = $existing ?? User::create($name, $mail, "demo-password-123", ["email_verified_at" => gmdate("Y-m-d H:i:s"), "karma" => 3000]);
            $users[] = $user;
        }

        $samples = [
            ["How do I keep a PHP session secure behind a load balancer?",
             "We run three web nodes behind a load balancer and users get logged out at random.\n\nWhat is the recommended way to share sessions?\n\n- sticky sessions\n- shared redis\n- database sessions\n\nWhich one would you pick and why?",
             ["php", "sessions", "load-balancing"]],
            ["What is the difference between InnoDB and MyISAM in 2026?",
             "The old tables in our project still use MyISAM. Is there any reason left to keep them, or should everything be InnoDB?\n\nWe mostly do reads with a few writes per second.",
             ["mysql", "mariadb", "innodb"]],
            ["Vue 3: when should I reach for a store instead of props?",
             "My component tree is four levels deep and I am passing the same object down all the way.\n\nAt what point is a store the better answer?",
             ["vue", "javascript", "state-management"]],
        ];

        foreach ($samples as $index => [$title, $body, $tags]) {
            $question = Question::create($title, $body, $tags, $users[$index % count($users)]->id());
            $answer = Answer::create($question->id(),
                "Short version: measure first.\n\n" .
                "In practice the shared store wins as soon as more than one node is involved, because it removes the failure mode entirely instead of making it less likely.\n\n" .
                "```php\n\$db = new SQL(0);\n\$row = \$db->cmdrow('SELECT * FROM users WHERE id = \"{0}\"', [\$id]);\n```\n\n" .
                "That is the setup we have been running for two years without a single incident.",
                $users[($index + 1) % count($users)]->id()
            );
            Comment::create("question", $question->id(), "Good question - I ran into the same thing last week.", $users[($index + 2) % count($users)]->id());
            $question->accept($answer->id(), $users[$index % count($users)]->id());
        }
        self::ok("Created " . count($samples) . " questions, answers and comments");
        self::ok("Demo accounts: ada / linus / grace, password 'demo-password-123'");
    }

    public static function ok(string $message): void   { echo "[ok]   " . $message . "\n"; }
    public static function warn(string $message): void { echo "[warn] " . $message . "\n"; }
    public static function die(string $message): void  { echo "[err]  " . $message . "\n"; exit(1); }
}
