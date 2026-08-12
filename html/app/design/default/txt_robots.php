<?php
header("Content-Type: text/plain; charset=utf-8");
?>
User-agent: *
Disallow: /account/
Disallow: /admin/
Disallow: /api/
Disallow: /search
Allow: /

Sitemap: <?= Config::baseUrl() ?>/sitemap.xml
