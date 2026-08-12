<?php

/**
 * i18n - translations.
 *
 * Keys are the English source strings, so an untranslated key still renders
 * something readable. A page loads its own dictionary with
 *
 *     i18n::init(__FILE__);        // loads page_questions.i18n.json next to it
 *     i18n::init("core");          // loads app/locale/core.<lang>.json
 *
 * and then uses the global helper:  __("Ask a question")
 *
 * File format (page_questions.i18n.json):
 *   { "en": { "Ask a question": "Ask a question" },
 *     "de": { "Ask a question": "Frage stellen" } }
 */
class i18n {

    private static array $_dict = [];
    private static array $_loaded = [];
    private static string $_lang = "en";

    public const LANGUAGES = ["en" => "English", "de" => "Deutsch"];

    public static function setLanguage(string $lang): void {
        $lang = strtolower(substr($lang, 0, 2));
        if (!isset(self::LANGUAGES[$lang])) $lang = "en";
        self::$_lang = $lang;
        $_ENV["lang"] = $lang;
    }

    public static function lang(): string {
        return self::$_lang;
    }

    /**
     * Detect the language from (1) the user profile, (2) ?_lang=, (3) the
     * browser and (4) the site default.
     */
    public static function detect(): void {
        $lang = null;
        if (!empty($_GET["_lang"])) $lang = (string)$_GET["_lang"];
        elseif (!empty($_SESSION["lang"])) $lang = (string)$_SESSION["lang"];
        elseif (!empty($_SERVER["HTTP_ACCEPT_LANGUAGE"])) {
            foreach (explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]) as $part) {
                $code = strtolower(substr(trim(explode(";", $part)[0]), 0, 2));
                if (isset(self::LANGUAGES[$code])) { $lang = $code; break; }
            }
        }
        if ($lang === null) $lang = (string)Config::get("site_language", "en");
        self::setLanguage($lang);
        if (!empty($_GET["_lang"])) $_SESSION["lang"] = self::$_lang;
    }

    /**
     * Load a dictionary. Pass __FILE__ from a template or a domain name.
     */
    public static function init(string $source): void {
        if (isset(self::$_loaded[$source])) return;
        self::$_loaded[$source] = true;

        if (str_ends_with($source, ".php")) {
            $file = substr($source, 0, -4) . ".i18n.json";
        } else {
            $file = ($_ENV["basepath"] ?? ".") . "/locale/" . basename($source) . ".i18n.json";
        }
        if (!is_file($file)) return;

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) return;
        foreach ($data as $lang => $pairs) {
            if (!is_array($pairs)) continue;
            foreach ($pairs as $key => $value) {
                self::$_dict[$lang][$key] = $value;
            }
        }
    }

    public static function translate(string $key): string {
        return self::$_dict[self::$_lang][$key] ?? $key;
    }

    /** Locale aware date formatting for a UTC "Y-m-d H:i:s" string. */
    public static function date(?string $datetime, string $format = "j M Y H:i"): string {
        if ($datetime === null || $datetime === "" || str_starts_with($datetime, "0000")) return "";
        $ts = strtotime($datetime . " UTC");
        if ($ts === false) return "";
        return date($format, $ts);
    }

    /** "3 hours ago" / "vor 3 Stunden" for a UTC "Y-m-d H:i:s" string. */
    public static function ago(?string $datetime): string {
        if ($datetime === null || $datetime === "") return "";
        $ts = strtotime($datetime . " UTC");
        if ($ts === false) return "";
        $d = time() - $ts;
        if ($d < 0) $d = 0;
        if ($d < 60)      return sprintf(__("%d seconds ago"), $d);
        if ($d < 3600)    return sprintf(__("%d minutes ago"), (int)floor($d / 60));
        if ($d < 86400)   return sprintf(__("%d hours ago"), (int)floor($d / 3600));
        if ($d < 2592000) return sprintf(__("%d days ago"), (int)floor($d / 86400));
        return self::date($datetime, "j M Y");
    }

    /** 1234567 -> "1.2m" for the counter boxes. */
    public static function shortNumber($value): string {
        $value = (int)$value;
        if ($value >= 1000000) return round($value / 1000000, 1) . "m";
        if ($value >= 10000)   return floor($value / 1000) . "k";
        return (string)$value;
    }
}

/**
 * Translate a string. Global by design - it is used in every template.
 */
function __(string $key): string {
    return i18n::translate($key);
}
