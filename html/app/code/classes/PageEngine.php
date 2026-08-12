<?php

/**
 * PageEngine - locates and renders templates.
 *
 * Templates are plain PHP files below html/app/design/<skin>/. A skin can
 * override single templates of the skin below it:
 *
 *     PageEngine::AddSkin("mycompany", 20);
 *     PageEngine::html("page_questions", ["tag" => "php"]);
 *
 * Inside a template the second argument is available as $params.
 */
class PageEngine {

    /** @var array<int,string> priority => skin name, highest priority wins */
    private static array $_skins = [10 => "default"];

    public static array $messages = [];
    public static array $debuglog = [];

    public static function AddSkin(string $skin, int $priority = 10): void {
        self::$_skins[$priority] = $skin;
        krsort(self::$_skins);
    }

    public static function skins(): array {
        krsort(self::$_skins);
        return self::$_skins;
    }

    public static function html_find(string $key, string $extension = ".php"): ?string {
        foreach (self::skins() as $skin) {
            $file = $_ENV["basepath"] . "/design/" . $skin . "/" . $key . $extension;
            if (is_file($file)) return $file;
        }
        return null;
    }

    /**
     * Render a template.
     *
     * @param string $key    template name below the skin directory
     * @param array  $params available as $params inside the template
     */
    public static function html(string $key, array $params = []): void {
        $file = self::html_find($key);
        if ($file === null) {
            self::$debuglog[] = "template not found: " . $key;
            if (($_ENV["STAGE"] ?? "") === "development") {
                echo "<!-- template not found: " . htmlspecialchars($key, ENT_QUOTES, "UTF-8") . " -->";
            }
            return;
        }
        self::$debuglog[] = "render: " . $key;
        include $file;
    }

    /** Render a template into a string instead of the output buffer. */
    public static function fetch(string $key, array $params = []): string {
        ob_start();
        self::html($key, $params);
        return (string)ob_get_clean();
    }

    public static function AddMessage(string $type, string $message): void {
        self::$messages[] = ["type" => $type, "text" => $message];
        $_SESSION["flash"][] = ["type" => $type, "text" => $message];
    }

    public static function AddErrorMessage(string $message): void   { self::AddMessage("danger", $message); }
    public static function AddSuccessMessage(string $message): void { self::AddMessage("success", $message); }
    public static function AddInfoMessage(string $message): void    { self::AddMessage("info", $message); }

    /** Read and clear the flash messages of the previous request. */
    public static function takeFlash(): array {
        $out = $_SESSION["flash"] ?? [];
        unset($_SESSION["flash"]);
        return is_array($out) ? $out : [];
    }

    public static function runtime(): float {
        return round(microtime(true) - ($_ENV["pgmstart"] ?? microtime(true)), 4);
    }

    /** Send a redirect and stop. */
    public static function goto(string $url, int $code = 302): void {
        header("Location: " . $url, true, $code);
        exit;
    }

    /** Render an error page and stop. */
    public static function error(int $code, string $message = ""): void {
        http_response_code($code);
        $template = "page_" . $code;
        if (self::html_find($template) === null) $template = "page_error";
        self::html($template, ["code" => $code, "message" => $message]);
        exit;
    }
}
