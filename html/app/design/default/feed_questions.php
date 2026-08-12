<?php
/**
 * feed_questions.php - RSS 2.0 of the newest questions.
 */
$limit = Config::int("feed_item_count", 30);
$result = Question::search(["sort" => "newest", "per_page" => $limit, "tag" => (string)($_GET["tag"] ?? "")]);

header("Content-Type: application/rss+xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= html(Config::get("site_title")) ?> - <?= html(__("newest questions")) ?></title>
  <link><?= html(Config::baseUrl()) ?></link>
  <description><?= html(Config::get("site_description")) ?></description>
  <language><?= html(i18n::lang()) ?></language>
  <atom:link href="<?= html(url("feeds/questions.rss")) ?>" rel="self" type="application/rss+xml"/>
<?php foreach ($result["items"] as $row) { ?>
  <item>
    <title><?= html($row["title"]) ?></title>
    <link><?= html(url("question/" . (int)$row["id"] . "/" . $row["slug"])) ?></link>
    <guid isPermaLink="true"><?= html(url("question/" . (int)$row["id"] . "/" . $row["slug"])) ?></guid>
    <pubDate><?= html(gmdate("r", strtotime((string)$row["created_at"] . " UTC"))) ?></pubDate>
    <description><?= html(Markdown::toText((string)$row["body_md"], 600)) ?></description>
<?php foreach (explode(",", (string)$row["tags"]) as $tag) { if ($tag === "") continue; ?>
    <category><?= html($tag) ?></category>
<?php } ?>
  </item>
<?php } ?>
</channel>
</rss>
