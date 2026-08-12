<?php

/**
 * Askbot - application metadata.
 */
class Askbot {

    public const VERSION = "1.0.0";
    public const CODENAME = "Reboot";

    /** Bumped whenever css/js change so browsers pick up the new files. */
    public const ASSET_VERSION = "1000";

    public static function userAgent(): string {
        return "askbot_php/" . self::VERSION . " (+https://github.com/andreaskasper/askbot_php)";
    }
}
