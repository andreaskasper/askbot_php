<?php
/**
 * feed_question.php - answers of one question.
 */
$question = new Question((int)$params["id"]);
if (!$question->exists()) PageEngine::error(404);

header("Content-Type: application/rss+xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0">
<channel>
  <title><?= html($question->title) ?></title>
  <link><?= html($question->url()) ?></link>
  <description><?= html(Markdown::toText((string)$question->body_md, 400)) ?></description>
<?php foreach (Answer::forQuestion($question->id(), "newest") as $row) { ?>
  <item>
    <title><?= html(sprintf(__("Answer with %d votes"), (int)$row["score"])) ?></title>
    <link><?= html($question->url() . "#answer-" . (int)$row["id"]) ?></link>
    <guid isPermaLink="false">answer-<?= (int)$row["id"] ?></guid>
    <pubDate><?= html(gmdate("r", strtotime((string)$row["created_at"] . " UTC"))) ?></pubDate>
    <description><?= html(Markdown::toText((string)$row["body_md"], 600)) ?></description>
  </item>
<?php } ?>
</channel>
</rss>
