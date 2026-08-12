<?php

/**
 * Auth - sign up, sign in, email verification, password reset, 2FA.
 */
class Auth {

    /** Register a new account and send the verification mail. */
    public static function register(string $username, string $email, string $password, string $passwordRepeat): User {
        if (!Config::bool("registration_open", true)) {
            throw new \RuntimeException(__("Registration is currently closed."));
        }
        if (!RateLimiter::check("signup:" . Firewall::ipHash(), 5, 3600)) {
            throw new \RuntimeException(__("Too many sign up attempts. Please try again later."));
        }
        if ($password !== $passwordRepeat) throw new \InvalidArgumentException(__("The two passwords do not match."));
        self::checkPasswordStrength($password);

        $user = User::create($username, $email, $password);

        if (Config::bool("require_email_verification", true) && $user->email_verified_at === null) {
            self::sendVerificationMail($user);
        }
        Audit::log("signup", "user:" . $user->id(), [], $user->id());
        return $user;
    }

    public static function checkPasswordStrength(string $password): void {
        if (mb_strlen($password) < 10) throw new \InvalidArgumentException(__("Please use a password with at least 10 characters."));
        $common = ["password", "12345678", "qwertzuiop", "qwertyuiop", "letmein123", "askbot123"];
        if (in_array(mb_strtolower($password), $common, true)) {
            throw new \InvalidArgumentException(__("This password is too common."));
        }
    }

    /**
     * Verify credentials.
     *
     * @return array{status:string,user:?User} status: ok | 2fa | invalid | suspended | unverified
     */
    public static function attempt(string $login, string $password): array {
        $ipBucket = "signin:" . Firewall::ipHash();
        if (!RateLimiter::check($ipBucket, 20, 900)) {
            throw new \RuntimeException(__("Too many sign in attempts. Please wait 15 minutes."));
        }

        $user = User::byLogin($login);
        if ($user === null || !$user->verifyPassword($password)) {
            // Same wording for both cases so no account can be probed.
            Audit::log("signin.failed", mb_substr($login, 0, 64));
            return ["status" => "invalid", "user" => null];
        }
        if ($user->isSuspended())  return ["status" => "suspended", "user" => $user];
        if (Config::bool("require_email_verification", true) && $user->email_verified_at === null) {
            return ["status" => "unverified", "user" => $user];
        }
        if ((int)$user->totp_enabled === 1) {
            $_SESSION["pending_2fa_user"] = $user->id();
            return ["status" => "2fa", "user" => $user];
        }
        return ["status" => "ok", "user" => $user];
    }

    public static function completeTwoFactor(string $code, bool $remember = false): bool {
        $userId = (int)($_SESSION["pending_2fa_user"] ?? 0);
        if ($userId === 0) return false;
        if (!RateLimiter::check("2fa:" . $userId, 10, 900)) throw new \RuntimeException(__("Too many attempts."));

        $user = new User($userId);
        if (!$user->exists() || !Totp::verify((string)$user->totp_secret, $code)) return false;
        MyUser::login($user, $remember);
        return true;
    }

    // -----------------------------------------------------------------------
    // Tokens
    // -----------------------------------------------------------------------

    public static function createToken(int $userId, string $type, int $ttlSeconds = 86400, string $label = ""): string {
        $token = rtrim(strtr(base64_encode(random_bytes(24)), "+/", "-_"), "=");
        $db = new SQL(0);
        $db->Create("user_tokens", [
            "user_id"    => $userId,
            "type"       => $type,
            "token_hash" => hash("sha256", $token),
            "label"      => $label,
            "expires_at" => $ttlSeconds > 0 ? gmdate("Y-m-d H:i:s", time() + $ttlSeconds) : null,
        ]);
        return $token;
    }

    /** Consume a one time token and return the user it belongs to. */
    public static function useToken(string $token, string $type): ?User {
        $db = new SQL(0);
        $row = $db->cmdrow(
            'SELECT * FROM user_tokens WHERE token_hash = "{0}" AND type = "{1}" AND used_at IS NULL
             AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) LIMIT 0,1',
            [hash("sha256", $token), $type]
        );
        if ($row === []) return null;
        $db->Update("user_tokens", ["used_at" => gmdate("Y-m-d H:i:s")], (int)$row["id"]);
        return new User((int)$row["user_id"]);
    }

    public static function sendVerificationMail(User $user): void {
        $token = self::createToken($user->id(), "verify_email", 3 * 86400);
        Mailer::queueTemplate(
            (string)$user->email,
            $user->displayName(),
            sprintf(__("Please confirm your email address for %s"), (string)Config::get("site_title", "Askbot")),
            "verify_email",
            ["user" => $user, "link" => url("account/verify/" . $token)]
        );
    }

    public static function sendPasswordReset(string $email): void {
        if (!RateLimiter::check("reset:" . Firewall::ipHash(), 5, 3600)) {
            throw new \RuntimeException(__("Too many requests. Please try again later."));
        }
        $user = User::byEmail($email);
        // Always behave the same way, whether the address exists or not.
        if ($user === null) return;

        $token = self::createToken($user->id(), "reset_password", 3600);
        Mailer::queueTemplate(
            (string)$user->email,
            $user->displayName(),
            sprintf(__("Reset your password for %s"), (string)Config::get("site_title", "Askbot")),
            "reset_password",
            ["user" => $user, "link" => url("account/reset/" . $token)]
        );
        Audit::log("password.reset_requested", "user:" . $user->id(), [], $user->id());
    }

    /** Personal API key, shown once in the account settings. */
    public static function createApiKey(int $userId, string $label): string {
        return self::createToken($userId, "api", 0, mb_substr($label, 0, 64));
    }

    public static function userByApiKey(string $token): ?User {
        $db = new SQL(0);
        $row = $db->cmdrow(
            'SELECT user_id FROM user_tokens WHERE token_hash = "{0}" AND type = "api" AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP()) LIMIT 0,1',
            [hash("sha256", $token)]
        );
        return $row === [] ? null : new User((int)$row["user_id"]);
    }
}
