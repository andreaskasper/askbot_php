<?php

namespace bots;

/**
 * sitemap - write a static sitemap so large sites do not build it per request.
 *
 * Schedule: daily.
 */
class sitemap {

    public static function run(array $data = []): string {
        $db = new \SQL(0);
        $rows = $db->cmdrows('SELECT id, title, slug, updated_at FROM questions WHERE deleted_at IS NULL ORDER BY last_activity_at DESC LIMIT 45000');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        $xml .= "  <url><loc>" . htmlspecialchars(\Config::baseUrl() . "/", ENT_QUOTES) . "</loc><changefreq>hourly</changefreq></url>\n";
        foreach (["questions", "tags", "users", "badges"] as $page) {
            $xml .= "  <url><loc>" . htmlspecialchars(\Config::baseUrl() . "/" . $page, ENT_QUOTES) . "</loc><changefreq>daily</changefreq></url>\n";
        }
        foreach ($rows as $row) {
            $loc = \Config::baseUrl() . "/question/" . (int)$row["id"] . "/" . $row["slug"];
            $xml .= "  <url><loc>" . htmlspecialchars($loc, ENT_QUOTES) . "</loc>"
                 . "<lastmod>" . gmdate("Y-m-d", strtotime((string)$row["updated_at"] . " UTC")) . "</lastmod></url>\n";
        }
        $xml .= "</urlset>\n";

        $target = $_ENV["webroot"] . "/sitemap.xml";
        file_put_contents($target, $xml);
        return count($rows) . " urls written to sitemap.xml";
    }
}
