<?php

/**
 * WebCache - small key/value cache.
 *
 * Uses APCu when the extension is loaded and falls back to files below
 * logs/cache so the app also works on plain shared hosting.
 */
class WebCache {

    private static ?bool $_apcu = null;

    private static function apcu(): bool {
        if (self::$_apcu === null) {
            self::$_apcu = function_exists("apcu_fetch") && (bool)ini_get("apc.enabled");
        }
        return self::$_apcu;
    }

    private static function file(string $key): string {
        $dir = dirname($_ENV["basepath"] ?? __DIR__, 2) . "/logs/cache";
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir . "/" . sha1($key) . ".cache";
    }

    public static function get(string $key, $default = null) {
        if (self::apcu()) {
            $ok = false;
            $value = apcu_fetch($key, $ok);
            return $ok ? $value : $default;
        }
        $file = self::file($key);
        if (!is_file($file)) return $default;
        $raw = (string)file_get_contents($file);
        $data = @unserialize($raw);
        if (!is_array($data) || !isset($data["expires"])) return $default;
        if ($data["expires"] > 0 && $data["expires"] < time()) { @unlink($file); return $default; }
        return $data["value"];
    }

    public static function set(string $key, $value, int $ttl = 300): void {
        if (self::apcu()) { apcu_store($key, $value, $ttl); return; }
        @file_put_contents(self::file($key), serialize([
            "expires" => $ttl > 0 ? time() + $ttl : 0,
            "value"   => $value,
        ]), LOCK_EX);
    }

    public static function delete(string $key): void {
        if (self::apcu()) { apcu_delete($key); return; }
        @unlink(self::file($key));
    }

    /** Read through helper: WebCache::remember("tags", 600, fn() => Tag::popular()); */
    public static function remember(string $key, int $ttl, callable $producer) {
        $value = self::get($key, null);
        if ($value !== null) return $value;
        $value = $producer();
        self::set($key, $value, $ttl);
        return $value;
    }

    public static function flush(): void {
        if (self::apcu()) { apcu_clear_cache(); return; }
        $dir = dirname($_ENV["basepath"] ?? __DIR__, 2) . "/logs/cache";
        foreach (glob($dir . "/*.cache") ?: [] as $file) @unlink($file);
    }
}
