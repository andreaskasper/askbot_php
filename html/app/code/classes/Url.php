<?php

/**
 * Url - build links that keep the current query string intact.
 */
class Url {

    /** Absolute URL of the current request, without the query string. */
    public static function current(): string {
        return url(ltrim(\web\Routing::$path, "/"));
    }

    /**
     * Current URL with some query parameters changed.
     * A value of null removes the parameter.
     */
    public static function withParams(array $params): string {
        $query = $_GET;
        foreach ($params as $key => $value) {
            if ($value === null) unset($query[$key]);
            else $query[$key] = $value;
        }
        $queryString = http_build_query($query);
        return self::current() . ($queryString !== "" ? "?" . $queryString : "");
    }

    /** Add or replace a single parameter. */
    public static function addVar(string $key, $value): string {
        return self::withParams([$key => $value]);
    }

    /** Safe internal redirect target, falls back to the home page. */
    public static function safeReturn(?string $candidate, string $fallback = "/"): string {
        $candidate = (string)$candidate;
        if ($candidate === "" || !str_starts_with($candidate, "/") || str_starts_with($candidate, "//")) {
            return url($fallback);
        }
        return url(ltrim($candidate, "/"));
    }
}
