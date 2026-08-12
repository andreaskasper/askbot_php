<?php

/**
 * User - a community member.
 *
 * Properties are loaded lazily, so `new User($id)` is free and
 * `$user->username` costs one query for the whole row.
 */
class User {

    private int $_id;
    private ?array $_row = null;

    public function __construct(int $id, ?array $row = null) {
        $this->_id = $id;
        if ($row !== null) $this->_row = $row;
    }

    public function id(): int { return $this->_id; }

    public function exists(): bool { return $this->row() !== []; }

    public function row(): array {
        if ($this->_row === null) {
            $db = new SQL(0);
            $this->_row = $db->cmdrow('SELECT * FROM users WHERE id = "{0}" LIMIT 0,1', [$this->_id]);
        }
        return $this->_row;
    }

    public function __get(string $name) {
        return $this->row()[$name] ?? null;
    }

    public function __isset(string $name): bool {
        return isset($this->row()[$name]);
    }

    public function refresh(): void { $this->_row = null; }

    // -----------------------------------------------------------------------
    // Lookups
    // -----------------------------------------------------------------------

    public static function byEmail(string $email): ?User {
        $db = new SQL(0);
        $row = $db->cmdrow('SELECT * FROM users WHERE email = "{0}" AND deleted_at IS NULL LIMIT 0,1', [strtolower(trim($email))]);
        return $row === [] ? null : new User((int)$row["id"], $row);
    }

    public static function byUsername(string $username): ?User {
        $db = new SQL(0);
        $row = $db->cmdrow('SELECT * FROM users WHERE username = "{0}" AND deleted_at IS NULL LIMIT 0,1', [trim($username)]);
        return $row === [] ? null : new User((int)$row["id"], $row);
    }

    /** Username or email, used by the sign in form. */
    public static function byLogin(string $login): ?User {
        $login = trim($login);
        return str_contains($login, "@") ? self::byEmail($login) : self::byUsername($login);
    }

    public static function byProvider(string $provider, string $uid): ?User {
        $db = new SQL(0);
        $userId = $db->cmdvalue('SELECT user_id FROM user_logins WHERE provider = "{0}" AND provider_uid = "{1}" LIMIT 0,1', [$provider, $uid]);
        return $userId === null ? null : new User((int)$userId);
    }

    // -----------------------------------------------------------------------
    // Creating and updating
    // -----------------------------------------------------------------------

    /**
     * @throws \InvalidArgumentException when the name or mail is unusable
     */
    public static function create(string $username, string $email, ?string $password = null, array $extra = []): User {
        $username = trim($username);
        $email = strtolower(trim($email));

        if (!self::isValidUsername($username)) throw new \InvalidArgumentException("Please choose a user name of 3 to 40 characters (letters, digits, dot, dash, underscore).");
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  throw new \InvalidArgumentException("Please enter a valid email address.");
        if (self::byUsername($username) !== null) throw new \InvalidArgumentException("This user name is already taken.");
        if (self::byEmail($email) !== null)       throw new \InvalidArgumentException("An account with this email address already exists.");

        $db = new SQL(0);
        $data = array_merge([
            "username"      => $username,
            "slug"          => Slug::make($username),
            "email"         => $email,
            "password_hash" => $password !== null ? self::hash($password) : null,
            "karma"         => Config::int("karma_new_user", 1),
            "locale"        => i18n::lang(),
        ], $extra);

        // The very first account owns the site.
        if ($db->cmdint('SELECT COUNT(*) FROM users') === 0) {
            $data["role"] = "admin";
            $data["email_verified_at"] = gmdate("Y-m-d H:i:s");
        }
        $id = $db->Create("users", $data);
        return new User($id);
    }

    public static function isValidUsername(string $username): bool {
        return (bool)preg_match('/^[\p{L}0-9][\p{L}0-9 ._-]{1,38}[\p{L}0-9._-]$/u', $username);
    }

    public function save(array $data): bool {
        $db = new SQL(0);
        $ok = $db->Update("users", $data, $this->_id);
        $this->refresh();
        return $ok;
    }

    /**
     * Argon2id when the build supports it, bcrypt otherwise. Not every PHP
     * package ships libargon2, and a missing constant must not be fatal.
     */
    public static function passwordAlgo(): string {
        return defined("PASSWORD_ARGON2ID") ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
    }

    public static function hash(string $password): string {
        return password_hash($password, self::passwordAlgo());
    }

    public function verifyPassword(string $password): bool {
        $hash = (string)$this->password_hash;
        if ($hash === "") return false;

        // Accounts imported from the 2013 database still carry an md5 hash.
        if (strlen($hash) === 32 && ctype_xdigit($hash)) {
            if (!hash_equals($hash, md5($password))) return false;
            $this->save(["password_hash" => self::hash($password)]);
            return true;
        }
        if (!password_verify($password, $hash)) return false;
        if (password_needs_rehash($hash, self::passwordAlgo())) {
            $this->save(["password_hash" => self::hash($password)]);
        }
        return true;
    }

    public function setPassword(string $password): void {
        $this->save(["password_hash" => self::hash($password)]);
    }

    // -----------------------------------------------------------------------
    // Presentation
    // -----------------------------------------------------------------------

    public function permalink(): string {
        return url("users/" . $this->_id . "/" . ($this->slug ?: Slug::make((string)$this->username)));
    }

    public function displayName(): string {
        return (string)($this->username ?? "deleted user");
    }

    /** Gravatar style identicon without leaking the address. */
    public function avatar(int $size = 32): string {
        $custom = (string)$this->avatar_url;
        if ($custom !== "") return $custom;
        $hash = md5(strtolower(trim((string)$this->email)));
        return "https://www.gravatar.com/avatar/" . $hash . "?s=" . $size . "&d=identicon";
    }

    public function isStaff(): bool {
        return in_array((string)$this->role, ["admin", "moderator"], true);
    }

    public function isSuspended(): bool {
        if ((int)$this->is_suspended !== 1) return false;
        $until = $this->suspended_until;
        if ($until === null) return true;
        if (strtotime((string)$until . " UTC") > time()) return true;
        $this->save(["is_suspended" => 0, "suspended_until" => null, "suspended_reason" => ""]);
        return false;
    }

    public function unreadNotifications(): int {
        $db = new SQL(0);
        return $db->cmdint('SELECT COUNT(*) FROM notifications WHERE user_id = "{0}" AND read_at IS NULL', [$this->_id]);
    }

    public function badgeCounts(): array {
        $db = new SQL(0);
        $rows = $db->cmdrows(
            'SELECT b.level, COUNT(*) AS c FROM user_badges ub JOIN badges b ON b.id = ub.badge_id WHERE ub.user_id = "{0}" GROUP BY b.level',
            [$this->_id]
        );
        $out = ["gold" => 0, "silver" => 0, "bronze" => 0];
        foreach ($rows as $row) $out[$row["level"]] = (int)$row["c"];
        return $out;
    }

    public function toArray(bool $withPrivate = false): array {
        $row = $this->row();
        if ($row === []) {
            return ["id" => $this->_id, "username" => "deleted user", "url" => "", "avatar" => "", "karma" => 0];
        }
        $out = [
            "id"          => (int)$row["id"],
            "username"    => $row["username"],
            "url"         => $this->permalink(),
            "avatar"      => $this->avatar(32),
            "karma"       => (int)$row["karma"],
            "role"        => $row["role"],
            "country"     => (int)$row["show_country"] === 1 ? $row["country"] : "",
            "created_at"  => $row["created_at"],
            "badges"      => $this->badgeCounts(),
        ];
        if ($withPrivate) {
            $out["email"] = $row["email"];
            $out["email_verified"] = $row["email_verified_at"] !== null;
            $out["locale"] = $row["locale"];
            $out["totp_enabled"] = (int)$row["totp_enabled"] === 1;
        }
        return $out;
    }

    /** Bulk load users for a list of ids - avoids the N+1 query in lists. */
    public static function loadMany(array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map("intval", $ids))));
        if ($ids === []) return [];
        $db = new SQL(0);
        $rows = $db->cmdrows('SELECT * FROM users WHERE id IN (' . implode(",", $ids) . ')', [], "id");
        $out = [];
        foreach ($rows as $id => $row) $out[(int)$id] = new User((int)$id, $row);
        return $out;
    }
}
