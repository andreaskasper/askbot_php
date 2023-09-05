<?php
header("Content-Type: text/xml; Charset: UTF-8");
header("Content-Type: application/xml; Charset: UTF-8");
echo('<?xml version="1.0" encoding="UTF-8"?>');
echo('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">'.PHP_EOL);

$db = new SQL(0);
$maxviews = max(1, $db->cmdvalue(0, 'SELECT max(count_views) FROM questions WHERE 1'));
$rows = $db->cmdrows(0, 'SELECT id, title, date_edited, date_action, count_views FROM questions ORDER BY id DESC');
foreach ($rows as $row) {
	echo('<url>
    <loc>'.Question::PermalinkByData($row["id"], $row["title"]).'</loc>
    <lastmod>'.date("Y-m-d", $row["date_action"]).'T'.date("H:i:s", $row["date_action"]).'+01:00</lastmod>
    <changefreq>daily</changefreq>
    <priority>'.(0.49+(0.5*$row["count_views"]/$maxviews)).'</priority>
  </url>'.PHP_EOL);
}
?>
<url>
    <loc><?=$_ENV["baseurl"] ?>questions</loc>
    <lastmod><?=date("Y-m-d");?>T01:00:00+01:00</lastmod>
    <changefreq>daily</changefreq>
    <priority>.99</priority>
 </url>
<url>
    <loc><?=$_ENV["baseurl"] ?>questions/ask</loc>
    <lastmod>2012-01-01T01:00:00+01:00</lastmod>
    <changefreq>monthly</changefreq>
    <priority>.98</priority>
</url>
<url>
    <loc><?=$_ENV["baseurl"] ?>questions?scope=unanswered</loc>
    <lastmod><?=date("Y-m-d");?>T01:00:00+01:00</lastmod>
    <changefreq>daily</changefreq>
    <priority>.97</priority>
</url>
<url>
    <loc><?=$_ENV["baseurl"] ?>tags</loc>
    <lastmod><?=date("Y-m-d");?>T01:00:00+01:00</lastmod>
    <changefreq>daily</changefreq>
    <priority>.1</priority>
</url>
<url>
    <loc><?=$_ENV["baseurl"] ?>users</loc>
    <lastmod><?=date("Y-m-d");?>T01:00:00+01:00</lastmod>
    <changefreq>daily</changefreq>
    <priority>.1</priority>
</url>
<url>
    <loc><?=$_ENV["baseurl"] ?>badges</loc>
    <lastmod>2012-01-01T01:00:00+01:00</lastmod>
    <changefreq>weekly</changefreq>
    <priority>.1</priority>
</url>
</urlset>
<?php
Observer::Raise("XML_Sitemap_Created", array("Remote_IP" => (isset($_SERVER["REMOTE_ADDR"])?$_SERVER["REMOTE_ADDR"]:""), "Remote_Host" => (isset($_SERVER["REMOTE_HOST"])?$_SERVER["REMOTE_ADDR"]:""), "count_urls" => count($rows)+1));
?>