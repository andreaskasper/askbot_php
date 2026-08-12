<?php
/**
 * xml_sitemap.php - built on the fly; the "sitemap" bot can write a static
 * file for large installations.
 */
$db = new SQL(0);
$rows = $db->cmdrows('SELECT id, slug, updated_at FROM questions WHERE deleted_at IS NULL ORDER BY last_activity_at DESC LIMIT 5000');

header("Content-Type: application/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?= html(Config::baseUrl() . "/") ?></loc><changefreq>hourly</changefreq><priority>1.0</priority></url>
<?php foreach (["questions", "tags", "users", "badges", "about", "faq", "help"] as $page) { ?>
  <url><loc><?= html(url($page)) ?></loc><changefreq>daily</changefreq></url>
<?php } ?>
<?php foreach ($rows as $row) { ?>
  <url>
    <loc><?= html(url("question/" . (int)$row["id"] . "/" . $row["slug"])) ?></loc>
    <lastmod><?= html(gmdate("Y-m-d", strtotime((string)$row["updated_at"] . " UTC"))) ?></lastmod>
  </url>
<?php } ?>
</urlset>
