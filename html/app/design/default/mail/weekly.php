<?php
/**
 * mail/weekly.php
 * @param array $params questions
 */
?>
<h1 style="font-size:20px;margin:0 0 12px"><?= html(__("From the tags you follow")) ?></h1>
<ul style="padding-left:18px">
<?php foreach ($params["questions"] as $question) { ?>
  <li style="margin-bottom:10px">
    <a href="<?= htmlattr(url("question/" . (int)$question["id"] . "/" . $question["slug"])) ?>" style="color:#0d6efd"><?= html($question["title"]) ?></a>
    <div style="font-size:12px;color:#6c757d">
      <?= html(sprintf(__("%d answers, score %d"), (int)$question["answer_count"], (int)$question["score"])) ?>
    </div>
  </li>
<?php } ?>
</ul>
