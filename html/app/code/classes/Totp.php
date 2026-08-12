<?php

/**
 * Totp - RFC 6238 time based one time passwords for two factor login.
 *
 * No dependency: the whole algorithm is a HMAC plus a truncation.
 */
class Totp {

    private const ALPHABET = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
    private const PERIOD = 30;
    private const DIGITS = 6;

    public static function generateSecret(int $length = 32): string {
        $secret = "";
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    /** otpauth:// URI for the QR code in the account settings. */
    public static function provisioningUri(string $secret, string $account, string $issuer): string {
        return "otpauth://totp/" . rawurlencode($issuer) . ":" . rawurlencode($account)
             . "?secret=" . $secret
             . "&issuer=" . rawurlencode($issuer)
             . "&algorithm=SHA1&digits=" . self::DIGITS . "&period=" . self::PERIOD;
    }

    public static function code(string $secret, ?int $timestamp = null): string {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        $binary = pack("N*", 0, $counter);
        $hash = hash_hmac("sha1", $binary, self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
               | ((ord($hash[$offset + 1]) & 0xff) << 16)
               | ((ord($hash[$offset + 2]) & 0xff) << 8)
               | (ord($hash[$offset + 3]) & 0xff);
        return str_pad((string)($value % (10 ** self::DIGITS)), self::DIGITS, "0", STR_PAD_LEFT);
    }

    /** Accepts the previous, current and next window (clock drift). */
    public static function verify(string $secret, string $code, int $window = 1): bool {
        $code = preg_replace('/\D/', "", $code) ?? "";
        if (strlen($code) !== self::DIGITS) return false;
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::code($secret, $now + ($i * self::PERIOD)), $code)) return true;
        }
        return false;
    }

    private static function base32Decode(string $secret): string {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/i', "", $secret) ?? "");
        $bits = "";
        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) continue;
            $bits .= str_pad(decbin($index), 5, "0", STR_PAD_LEFT);
        }
        $out = "";
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr((int)bindec($byte));
        }
        return $out;
    }
}
