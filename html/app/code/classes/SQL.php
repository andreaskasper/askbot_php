<?php

/**
 * SQL - thin PDO wrapper.
 *
 * Connections are registered once (usually in html/index.php) and addressed by
 * number, so a page can talk to more than one database without passing handles
 * around:
 *
 *     SQL::init(0, "mysql://user:pass@host:3306/database/");
 *     $db = new SQL(0);
 *
 * Values are never concatenated into a statement. Instead the statement uses
 * numbered placeholders which are escaped on substitution:
 *
 *     $db->cmdrow('SELECT * FROM users WHERE id = "{0}" LIMIT 0,1', [$id]);
 *     $db->cmdrows('SELECT * FROM questions WHERE tags LIKE "%{0}%"', [$tag]);
 *
 * IMPORTANT: a placeholder must always be written inside quotes. For values
 * that cannot be quoted (LIMIT, ORDER BY) use SQL::int() / SQL::identifier().
 *
 * @author Andreas Kasper
 */
class SQL {

    /** @var array<int,array> connection settings per connection number */
    private static array $_conn = [];

    /** @var array<int,\PDO> live handles per connection number */
    private static array $_link = [];

    public static int $counter = 0;
    public static float $timer = 0;
    public static array $history = [];
    public static bool $savehistory = false;

    private int $_nr = 0;
    private $_result = null;
    private bool $_success = true;

    public string $lastcmd = "";
    public string $lasterror = "";
    public int $lasterrornr = 0;
    public int $insertid = 0;
    public int $affected = 0;

    public function __construct(int $connectionNr = 0) {
        $this->_nr = $connectionNr;
    }

    /**
     * Register a connection. Nothing is opened yet - the first query connects.
     *
     * @param int    $nr    connection number
     * @param string $dburi mysql://user:password@host[:port]/database[/prefix]
     */
    public static function init(int $nr, string $dburi): bool {
        $a = parse_url($dburi);
        if ($a === false || !isset($a["scheme"]) || $a["scheme"] !== "mysql") {
            throw new SQLException("Only mysql:// connection URIs are supported", 601);
        }
        $path = explode("/", trim($a["path"] ?? "", "/"));
        self::$_conn[$nr] = [
            "host"     => $a["host"] ?? "localhost",
            "port"     => (int)($a["port"] ?? 3306),
            "socket"   => null,
            "user"     => isset($a["user"]) ? rawurldecode($a["user"]) : "root",
            "password" => isset($a["pass"]) ? rawurldecode($a["pass"]) : "",
            "database" => $path[0] ?? "",
            "prefix"   => $path[1] ?? "",
        ];
        // "mysql://user:pass@localhost/db/?socket=/tmp/mysql.sock"
        if (!empty($a["query"])) {
            parse_str($a["query"], $q);
            if (!empty($q["socket"])) self::$_conn[$nr]["socket"] = $q["socket"];
        }
        return true;
    }

    public static function isInitialised(int $nr = 0): bool {
        return isset(self::$_conn[$nr]);
    }

    /** Close every open handle (used by the CLI runner between loops). */
    public static function closeAll(): void {
        foreach (array_keys(self::$_link) as $nr) {
            unset(self::$_link[$nr]);
        }
    }

    /**
     * Lazily open (and cache) the PDO handle for this connection number.
     */
    public function link(): \PDO {
        if (isset(self::$_link[$this->_nr])) return self::$_link[$this->_nr];
        if (!isset(self::$_conn[$this->_nr])) {
            throw new SQLException("Connection " . $this->_nr . " is not configured", 601);
        }
        $c = self::$_conn[$this->_nr];

        $dsn = "mysql:dbname=" . $c["database"] . ";charset=utf8mb4;";
        $dsn .= $c["socket"] !== null ? "unix_socket=" . $c["socket"] : "host=" . $c["host"] . ";port=" . $c["port"];

        $link = null;
        $lastError = "";
        // A freshly started database container may need a few seconds.
        for ($try = 0; $try < 30; $try++) {
            try {
                $link = new \PDO($dsn, $c["user"], $c["password"], [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                    \PDO::ATTR_STRINGIFY_FETCHES  => false,
                ]);
                break;
            } catch (\PDOException $e) {
                $lastError = $e->getMessage();
                $link = null;
                usleep(500000);
            }
        }
        if ($link === null) {
            throw new SQLException("Database connection failed: " . $lastError, 602);
        }
        $link->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");
        $link->exec("SET SESSION time_zone = '+00:00'");
        self::$_link[$this->_nr] = $link;
        return $link;
    }

    /** Table prefix configured for this connection (usually empty). */
    public function prefix(): string {
        return self::$_conn[$this->_nr]["prefix"] ?? "";
    }

    /** Escaped value WITHOUT the surrounding quotes - for {0} placeholders. */
    public function escape(?string $value): string {
        $quoted = $this->link()->quote((string)$value);
        return substr($quoted, 1, -1);
    }

    /** Cast to int - use for LIMIT/OFFSET where quoting is not allowed. */
    public static function int($value): int {
        return (int)$value;
    }

    /** Escape a column or table name for the rare dynamic ORDER BY. */
    public static function identifier(string $name): string {
        return "`" . str_replace("`", "", $name) . "`";
    }

    /**
     * Replace {0}, {1}, {name} placeholders with escaped values.
     */
    private function bind(string $sql, array $values): string {
        if ($values === []) return $sql;
        foreach ($values as $k => $v) {
            if ($v === null)              $replacement = "";
            elseif (is_bool($v))          $replacement = $v ? "1" : "0";
            elseif (is_int($v) || is_float($v)) $replacement = (string)$v;
            elseif ($v instanceof \DateTimeInterface) $replacement = $v->format("Y-m-d H:i:s");
            else                          $replacement = $this->escape((string)$v);
            $sql = str_replace("{" . $k . "}", $replacement, $sql);
        }
        return $sql;
    }

    /**
     * Run a statement. Returns the PDOStatement for SELECTs.
     */
    public function cmd(string $sql, array $values = []) {
        $sql = $this->bind($sql, $values);
        $this->lastcmd = $sql;
        self::$counter++;

        $link = $this->link();
        $start = microtime(true);
        try {
            $statement = $link->query($sql);
            $this->_success = true;
        } catch (\PDOException $e) {
            $this->_success = false;
            $this->lasterrornr = (int)$e->getCode();
            $this->lasterror = $e->getMessage();
            $duration = microtime(true) - $start;
            self::$timer += $duration;
            if (self::$savehistory) self::$history[] = ["cmd" => $sql, "time" => $duration, "error" => $this->lasterror];
            throw new SQLException("Query failed: " . $this->lasterror, 603, $sql);
        }
        $duration = microtime(true) - $start;
        self::$timer += $duration;

        $this->lasterrornr = 0;
        $this->lasterror = "";
        $this->insertid = (int)$link->lastInsertId();
        $this->affected = $statement === false ? 0 : (int)$statement->rowCount();
        $this->_result = $statement;

        if (self::$savehistory) {
            self::$history[] = ["cmd" => $sql, "time" => $duration, "error" => ""];
            if (count(self::$history) > 200) array_shift(self::$history);
        }
        return $this->_result;
    }

    /** Run several statements separated by ";" (used by the installer). */
    public function multicmd(string $sql): bool {
        $this->link()->exec($sql);
        return true;
    }

    /** First row of the result as an associative array, [] when empty. */
    public function cmdrow(string $sql, array $values = []): array {
        $statement = $this->cmd($sql, $values);
        if (!($statement instanceof \PDOStatement)) return [];
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? $row : [];
    }

    /**
     * All rows as a list of associative arrays.
     *
     * @param string|null $key when given, that column becomes the array key
     */
    public function cmdrows(string $sql, array $values = [], ?string $key = null): array {
        $statement = $this->cmd($sql, $values);
        if (!($statement instanceof \PDOStatement)) return [];
        $out = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            if ($key !== null && isset($row[$key])) $out[$row[$key]] = $row;
            else $out[] = $row;
        }
        $statement->closeCursor();
        return $out;
    }

    /** First column of the first row, or null. */
    public function cmdvalue(string $sql, array $values = []) {
        $statement = $this->cmd($sql, $values);
        if (!($statement instanceof \PDOStatement)) return null;
        $row = $statement->fetch(\PDO::FETCH_NUM);
        $statement->closeCursor();
        return ($row === false || $row === null) ? null : $row[0];
    }

    /** Same as cmdvalue() but always an int - handy for COUNT(*). */
    public function cmdint(string $sql, array $values = []): int {
        return (int)$this->cmdvalue($sql, $values);
    }

    /**
     * INSERT a row. Returns the new auto increment id.
     *
     * @param array<string,mixed> $arr column => value
     */
    public function Create(string $table, array $arr, bool $ignore = false): int {
        $cols = [];
        $vals = [];
        foreach ($arr as $col => $value) {
            $cols[] = self::identifier($col);
            $vals[] = $this->literal($value);
        }
        $sql = "INSERT " . ($ignore ? "IGNORE " : "") . "INTO " . self::identifier($this->prefix() . $table)
             . " (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")";
        $this->cmd($sql);
        return $this->insertid;
    }

    /**
     * UPDATE a row by primary key (or by the given where columns).
     *
     * @param array<string,mixed>  $arr   column => value
     * @param int|string|array     $where id, or column => value conditions
     */
    public function Update(string $table, array $arr, $where): bool {
        $set = [];
        foreach ($arr as $col => $value) {
            $set[] = self::identifier($col) . "=" . $this->literal($value);
        }
        if (!is_array($where)) $where = ["id" => $where];
        $cond = [];
        foreach ($where as $col => $value) {
            $cond[] = self::identifier($col) . "=" . $this->literal($value);
        }
        if ($set === [] || $cond === []) return false;
        $sql = "UPDATE " . self::identifier($this->prefix() . $table)
             . " SET " . implode(",", $set) . " WHERE " . implode(" AND ", $cond);
        $this->cmd($sql);
        return $this->success();
    }

    /** INSERT ... ON DUPLICATE KEY UPDATE for the given columns. */
    public function CreateUpdate(string $table, array $arr, ?array $updateColumns = null): int {
        $cols = [];
        $vals = [];
        foreach ($arr as $col => $value) {
            $cols[] = self::identifier($col);
            $vals[] = $this->literal($value);
        }
        $upd = [];
        foreach (($updateColumns ?? array_keys($arr)) as $col) {
            $upd[] = self::identifier($col) . "=VALUES(" . self::identifier($col) . ")";
        }
        $sql = "INSERT INTO " . self::identifier($this->prefix() . $table)
             . " (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ")"
             . " ON DUPLICATE KEY UPDATE " . implode(",", $upd);
        $this->cmd($sql);
        return $this->insertid;
    }

    public function Delete(string $table, array $where): bool {
        $cond = [];
        foreach ($where as $col => $value) {
            $cond[] = self::identifier($col) . "=" . $this->literal($value);
        }
        if ($cond === []) return false;
        $this->cmd("DELETE FROM " . self::identifier($this->prefix() . $table) . " WHERE " . implode(" AND ", $cond));
        return $this->success();
    }

    /** Quote a PHP value for direct use in a statement. */
    public function literal($value): string {
        if ($value === null) return "NULL";
        if (is_bool($value)) return $value ? "1" : "0";
        if (is_int($value) || is_float($value)) return (string)$value;
        if ($value instanceof \DateTimeInterface) return '"' . $value->format("Y-m-d H:i:s") . '"';
        return '"' . $this->escape((string)$value) . '"';
    }

    public function success(): bool { return $this->_success; }
    public function insertid(): int { return $this->insertid; }
    public function affected(): int { return $this->affected; }

    public function begin(): void    { if (!$this->link()->inTransaction()) $this->link()->beginTransaction(); }
    public function commit(): void   { if ($this->link()->inTransaction()) $this->link()->commit(); }
    public function rollback(): void { if ($this->link()->inTransaction()) $this->link()->rollBack(); }

    /** True when the table exists in the current database. */
    public function tableExists(string $table): bool {
        $row = $this->cmdrow('SHOW TABLES LIKE "{0}"', [$this->prefix() . $table]);
        return $row !== [];
    }
}
