<?php
header("Content-Type: application/manifest+json; charset=utf-8");
echo json_encode([
    "name"             => Config::get("site_title"),
    "short_name"       => mb_substr((string)Config::get("site_title"), 0, 12),
    "description"      => Config::get("site_description"),
    "start_url"        => Config::baseUrl() . "/",
    "display"          => "standalone",
    "background_color" => "#ffffff",
    "theme_color"      => "#0d6efd",
    "icons"            => [],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
