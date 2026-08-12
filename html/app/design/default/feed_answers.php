<?php
/**
 * feed_answers.php - RSS 2.0 of the newest answers.
 */
$db = new SQL(0);
$rows = $db->cmdrows(
    'SELECT a.id, a.body_md, a.created_at, q.id AS question_id, q.title, q.slug
     FROM answers a JOIN questions q ON q.id = a.question_id
     WHERE a.deleted_at IS NULL AND q.deleted_at IS NULL ORDER BY a.created_at DESC LIMIT ' . SQL::int(Config::int("feed_item_count", 30))
);

header("Content-Type: application/rss+xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= html(Config::get("site_title")) ?> - <?= html(__("newest answers")) ?></title>
  <link><?= html(Config::baseUrl()) ?></link>
  <description><?= html(Config::get("site_description")) ?></description>
  <atom:link href="<?= html(url("feeds/answers.rss")) ?>" rel="self" type="application/rss+xml"/>
<?php foreach ($rows as $row) { ?>
  <item>
    <title><?= html($row["title"]) ?></title>
    <link><?= html(url("question/" . (int)$row["question_id"] . "/" . $row["slug"] . "#answer-" . (int)$row["id"])) ?></link>
    <guid isPermaLink="false">answer-<?= (int)$row["id"] ?></guid>
    <pubDate><?= html(gmdate("r", strtotime((string)$row["created_at"] . " UTC"))) ?></pubDate>
    <description><?= html(Markdown::toText((string)$row["body_md"], 600)) ?></description>
  </item>
<?php } ?>
</channel>
</rss>
