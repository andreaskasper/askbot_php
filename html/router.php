<?php
/**
 * router.php - only used by PHP's built in web server:
 *
 *     php -S localhost:8080 -t html html/router.php
 *
 * Apache and nginx use html/.htaccess and the vhost configuration instead.
 */
$path = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH) ?? "/";

if ($path !== "/" && is_file(__DIR__ . $path) && !str_ends_with($path, ".php")) {
    return false;   // let the built in server serve the asset
}
require __DIR__ . "/index.php";
