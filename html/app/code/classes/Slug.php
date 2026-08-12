<?php

/**
 * Slug - URL friendly strings.
 */
class Slug {

    private const TRANSLITERATE = [
        "ä" => "ae", "ö" => "oe", "ü" => "ue", "ß" => "ss",
        "Ä" => "Ae", "Ö" => "Oe", "Ü" => "Ue",
        "á" => "a", "à" => "a", "â" => "a", "å" => "a", "ã" => "a",
        "é" => "e", "è" => "e", "ê" => "e", "ë" => "e",
        "í" => "i", "ì" => "i", "î" => "i", "ï" => "i",
        "ó" => "o", "ò" => "o", "ô" => "o", "õ" => "o", "ø" => "o",
        "ú" => "u", "ù" => "u", "û" => "u",
        "ñ" => "n", "ç" => "c", "ý" => "y", "æ" => "ae", "œ" => "oe",
        "š" => "s", "ž" => "z", "č" => "c", "ř" => "r", "ě" => "e",
        "ł" => "l", "ą" => "a", "ę" => "e", "ż" => "z", "ź" => "z", "ć" => "c", "ń" => "n",
    ];

    public static function make(string $text, int $maxLength = 80): string {
        $text = strtr($text, self::TRANSLITERATE);
        $text = mb_strtolower($text, "UTF-8");
        if (function_exists("iconv")) {
            $converted = @iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $text);
            if ($converted !== false) $text = $converted;
        }
        $text = preg_replace('/[^a-z0-9]+/', "-", $text) ?? $text;
        $text = trim((string)$text, "-");
        if ($text === "") $text = "post";
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            $text = rtrim($text, "-");
        }
        return $text;
    }

    /** Tag names: lower case, no spaces, max 48 chars. */
    public static function tag(string $name): string {
        $name = mb_strtolower(trim($name), "UTF-8");
        $name = str_replace([" ", "_"], "-", $name);
        $name = preg_replace('/[^a-z0-9+#.\-]/u', "", $name) ?? $name;
        $name = trim((string)$name, "-");
        return mb_substr($name, 0, 48);
    }
}
