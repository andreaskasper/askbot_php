<?php

/**
 * OAuth - sign in with Google, GitHub or Discord.
 *
 * Providers are configured with environment variables; a provider without a
 * client id simply does not show up on the sign in page.
 */
class OAuth {

    private const PROVIDERS = [
        "google" => [
            "name"     => "Google",
            "icon"     => "fa-brands fa-google",
            "auth"     => "https://accounts.google.com/o/oauth2/v2/auth",
            "token"    => "https://oauth2.googleapis.com/token",
            "userinfo" => "https://openidconnect.googleapis.com/v1/userinfo",
            "scope"    => "openid email profile",
        ],
        "github" => [
            "name"     => "GitHub",
            "icon"     => "fa-brands fa-github",
            "auth"     => "https://github.com/login/oauth/authorize",
            "token"    => "https://github.com/login/oauth/access_token",
            "userinfo" => "https://api.github.com/user",
            "scope"    => "read:user user:email",
        ],
        "discord" => [
            "name"     => "Discord",
            "icon"     => "fa-brands fa-discord",
            "auth"     => "https://discord.com/api/oauth2/authorize",
            "token"    => "https://discord.com/api/oauth2/token",
            "userinfo" => "https://discord.com/api/users/@me",
            "scope"    => "identify email",
        ],
    ];

    /** Providers that are actually configured. */
    public static function available(): array {
        $out = [];
        foreach (self::PROVIDERS as $key => $provider) {
            if (self::clientId($key) !== "") {
                $out[$key] = ["name" => $provider["name"], "icon" => $provider["icon"]];
            }
        }
        return $out;
    }

    private static function clientId(string $provider): string {
        return (string)Config::env("OAUTH_" . strtoupper($provider) . "_ID", "");
    }

    private static function clientSecret(string $provider): string {
        return (string)Config::env("OAUTH_" . strtoupper($provider) . "_SECRET", "");
    }

    public static function redirectUri(string $provider): string {
        return url("account/oauth/" . $provider . "/callback");
    }

    /** Step 1: send the visitor to the provider. */
    public static function authorizeUrl(string $provider): string {
        if (!isset(self::PROVIDERS[$provider]) || self::clientId($provider) === "") {
            throw new \RuntimeException(__("This sign in method is not available."));
        }
        $state = bin2hex(random_bytes(16));
        $_SESSION["oauth_state"] = $state;
        $_SESSION["oauth_provider"] = $provider;

        return self::PROVIDERS[$provider]["auth"] . "?" . http_build_query([
            "client_id"     => self::clientId($provider),
            "redirect_uri"  => self::redirectUri($provider),
            "response_type" => "code",
            "scope"         => self::PROVIDERS[$provider]["scope"],
            "state"         => $state,
        ]);
    }

    /**
     * Step 2: exchange the code and return the local user.
     */
    public static function complete(string $provider, string $code, string $state): User {
        if (!isset(self::PROVIDERS[$provider])) throw new \RuntimeException("Unknown provider");
        if (empty($_SESSION["oauth_state"]) || !hash_equals((string)$_SESSION["oauth_state"], $state)) {
            throw new \RuntimeException(__("The sign in request expired. Please try again."));
        }
        unset($_SESSION["oauth_state"]);

        $token = self::post(self::PROVIDERS[$provider]["token"], [
            "client_id"     => self::clientId($provider),
            "client_secret" => self::clientSecret($provider),
            "code"          => $code,
            "grant_type"    => "authorization_code",
            "redirect_uri"  => self::redirectUri($provider),
        ]);
        $accessToken = (string)($token["access_token"] ?? "");
        if ($accessToken === "") throw new \RuntimeException(__("The provider did not return an access token."));

        $profile = self::get(self::PROVIDERS[$provider]["userinfo"], $accessToken);
        [$uid, $email, $name] = self::normalizeProfile($provider, $profile, $accessToken);
        if ($uid === "") throw new \RuntimeException(__("The provider did not return an account id."));

        $existing = User::byProvider($provider, $uid);
        if ($existing !== null) return $existing;

        // Link to an existing local account when the mail address matches.
        $user = $email !== "" ? User::byEmail($email) : null;
        if ($user === null) {
            if (!Config::bool("registration_open", true)) throw new \RuntimeException(__("Registration is currently closed."));
            $username = self::uniqueUsername($name !== "" ? $name : $provider . "-user");
            $user = User::create($username, $email !== "" ? $email : $uid . "@" . $provider . ".local", null, [
                "email_verified_at" => $email !== "" ? gmdate("Y-m-d H:i:s") : null,
            ]);
        }
        $db = new SQL(0);
        $db->CreateUpdate("user_logins", [
            "user_id"      => $user->id(),
            "provider"     => $provider,
            "provider_uid" => $uid,
            "display_name" => mb_substr($name, 0, 190),
        ], ["display_name"]);
        return $user;
    }

    private static function normalizeProfile(string $provider, array $profile, string $accessToken): array {
        return match ($provider) {
            "google"  => [(string)($profile["sub"] ?? ""), (string)($profile["email"] ?? ""), (string)($profile["name"] ?? "")],
            "github"  => [(string)($profile["id"] ?? ""), self::githubEmail($profile, $accessToken), (string)($profile["login"] ?? "")],
            "discord" => [(string)($profile["id"] ?? ""), (string)($profile["email"] ?? ""), (string)($profile["username"] ?? "")],
            default   => ["", "", ""],
        };
    }

    private static function githubEmail(array $profile, string $accessToken): string {
        if (!empty($profile["email"])) return (string)$profile["email"];
        $emails = self::get("https://api.github.com/user/emails", $accessToken);
        foreach ($emails as $entry) {
            if (!empty($entry["primary"]) && !empty($entry["verified"])) return (string)$entry["email"];
        }
        return "";
    }

    private static function uniqueUsername(string $base): string {
        $base = trim(preg_replace('/[^\p{L}0-9 ._-]/u', "", $base) ?? "user");
        if (mb_strlen($base) < 3) $base = "user" . random_int(1000, 9999);
        $candidate = $base;
        for ($i = 0; $i < 20; $i++) {
            if (User::byUsername($candidate) === null) return $candidate;
            $candidate = $base . random_int(10, 9999);
        }
        return $base . bin2hex(random_bytes(3));
    }

    private static function post(string $url, array $data): array {
        $context = stream_context_create(["http" => [
            "method"        => "POST",
            "header"        => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: " . Askbot::userAgent() . "\r\n",
            "content"       => http_build_query($data),
            "timeout"       => 15,
            "ignore_errors" => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) throw new \RuntimeException(__("Could not reach the sign in provider."));
        $json = json_decode($response, true);
        if (!is_array($json)) { parse_str($response, $json); }
        return is_array($json) ? $json : [];
    }

    private static function get(string $url, string $accessToken): array {
        $context = stream_context_create(["http" => [
            "method"        => "GET",
            "header"        => "Authorization: Bearer " . $accessToken . "\r\nAccept: application/json\r\nUser-Agent: " . Askbot::userAgent() . "\r\n",
            "timeout"       => 15,
            "ignore_errors" => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) throw new \RuntimeException(__("Could not reach the sign in provider."));
        $json = json_decode($response, true);
        return is_array($json) ? $json : [];
    }
}
