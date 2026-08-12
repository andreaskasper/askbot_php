<?php
/**
 * box_question_summary.php - one row in a question list.
 *
 * @param array $params row (question row), highlight_tag
 */
$row = $params["row"];
$author = $params["author"] ?? null;
$accepted = $row["accepted_answer_id"] !== null;
$answers = (int)$row["answer_count"];
?>
<article class="question-summary d-flex gap-3" id="question-<?= (int)$row["id"] ?>">
  <div class="d-flex gap-2 flex-shrink-0">
    <div class="question-stats">
      <span class="value"><?= html(i18n::shortNumber($row["score"])) ?></span>
      <?= html(__("votes")) ?>
    </div>
    <div class="question-stats <?= $accepted ? "is-accepted" : ($answers > 0 ? "has-answers" : "") ?>">
      <span class="value"><?= html(i18n::shortNumber($answers)) ?></span>
      <?= html(__("answers")) ?>
    </div>
    <div class="question-stats d-none d-sm-block">
      <span class="value"><?= html(i18n::shortNumber($row["view_count"])) ?></span>
      <?= html(__("views")) ?>
    </div>
  </div>

  <div class="flex-grow-1 min-w-0">
    <h2>
      <a class="text-decoration-none" href="<?= htmlattr(url("question/" . (int)$row["id"] . "/" . $row["slug"])) ?>"><?= html($row["title"]) ?></a>
      <?php if ((int)$row["is_closed"] === 1) { ?><span class="badge text-bg-secondary align-middle"><?= html(__("closed")) ?></span><?php } ?>
      <?php if ((int)$row["bounty_amount"] > 0) { ?><span class="badge text-bg-warning align-middle">+<?= (int)$row["bounty_amount"] ?></span><?php } ?>
    </h2>

    <p class="text-secondary small mb-2 d-none d-md-block">
      <?= html(Markdown::toText($row["body_md"] ?? "", 180)) ?>
    </p>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2">
      <div>
        <?php foreach (explode(",", (string)$row["tags"]) as $tag) { if ($tag === "") continue; ?>
          <a class="tag-chip" href="<?= htmlattr(url("questions?tag=" . rawurlencode($tag))) ?>"><?= html($tag) ?></a>
        <?php } ?>
      </div>
      <div class="small text-secondary text-nowrap">
        <?php if ($author !== null && $author->exists()) { ?>
          <img src="<?= htmlattr($author->avatar(16)) ?>" width="16" height="16" class="rounded" alt="">
          <a class="link-secondary text-decoration-none" href="<?= htmlattr($author->permalink()) ?>"><?= html($author->displayName()) ?></a>
          <span class="badge text-bg-light"><?= (int)$author->karma ?></span>
        <?php } else { ?>
          <span><?= html(__("deleted user")) ?></span>
        <?php } ?>
        <span title="<?= htmlattr(i18n::date($row["last_activity_at"], "Y-m-d H:i")) ?>">
          <?= html(i18n::ago((string)$row["last_activity_at"])) ?>
        </span>
      </div>
    </div>
  </div>
</article>
