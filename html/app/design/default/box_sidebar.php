<?php
/**
 * box_sidebar.php - the right hand column.
 *
 * @param array $params show (array of widgets), question (optional)
 */
$show = $params["show"] ?? ["stats", "tags", "related"];
$stats = Statistics::overview();
?>
<div class="sticky-sidebar">

<?php if (in_array("ask", $show, true)) { ?>
  <a class="btn btn-primary w-100 mb-3" href="<?= htmlattr(url("questions/ask")) ?>">
    <i class="fa-solid fa-plus me-1"></i><?= html(__("Ask a question")) ?>
  </a>
<?php } ?>

<?php if (in_array("stats", $show, true)) { ?>
  <div class="card mb-3">
    <div class="card-header py-2 small fw-semibold"><?= html(__("Community")) ?></div>
    <div class="card-body py-2">
      <div class="row text-center small g-2">
        <div class="col-4"><div class="fw-semibold"><?= html(i18n::shortNumber($stats["questions"])) ?></div><div class="text-secondary"><?= html(__("questions")) ?></div></div>
        <div class="col-4"><div class="fw-semibold"><?= html(i18n::shortNumber($stats["answers"])) ?></div><div class="text-secondary"><?= html(__("answers")) ?></div></div>
        <div class="col-4"><div class="fw-semibold"><?= html(i18n::shortNumber($stats["users"])) ?></div><div class="text-secondary"><?= html(__("users")) ?></div></div>
      </div>
      <?php if ($stats["unanswered"] > 0) { ?>
        <hr class="my-2">
        <a class="small text-decoration-none" href="<?= htmlattr(url("questions?scope=unanswered")) ?>">
          <?= html(sprintf(__("%d questions are still waiting for an answer"), $stats["unanswered"])) ?>
        </a>
      <?php } ?>
    </div>
  </div>
<?php } ?>

<?php if (in_array("related", $show, true) && !empty($params["related"])) { ?>
  <div class="card mb-3">
    <div class="card-header py-2 small fw-semibold"><?= html(__("Related questions")) ?></div>
    <ul class="list-group list-group-flush small">
      <?php foreach ($params["related"] as $related) { ?>
        <li class="list-group-item py-2">
          <span class="badge text-bg-light float-end ms-2"><?= (int)$related["answer_count"] ?></span>
          <a class="text-decoration-none" href="<?= htmlattr(url("question/" . (int)$related["id"] . "/" . ($related["slug"] ?? ""))) ?>"><?= html($related["title"]) ?></a>
        </li>
      <?php } ?>
    </ul>
  </div>
<?php } ?>

<?php if (in_array("tags", $show, true)) { ?>
  <div class="card mb-3">
    <div class="card-header py-2 small fw-semibold"><?= html(__("Popular tags")) ?></div>
    <div class="card-body py-2">
      <?php foreach (Tag::popular(24) as $tag) { ?>
        <a class="tag-chip" href="<?= htmlattr(url("questions?tag=" . rawurlencode($tag["name"]))) ?>">
          <?= html($tag["name"]) ?> <span class="tag-count">&times;<?= html(i18n::shortNumber($tag["question_count"])) ?></span>
        </a>
      <?php } ?>
    </div>
  </div>
<?php } ?>

<?php if (in_array("help", $show, true)) { ?>
  <div class="card mb-3">
    <div class="card-header py-2 small fw-semibold"><?= html(__("How to ask")) ?></div>
    <div class="card-body py-2 small text-secondary">
      <ul class="mb-0 ps-3">
        <li><?= html(__("Summarise the problem in the title.")) ?></li>
        <li><?= html(__("Describe what you tried and what happened.")) ?></li>
        <li><?= html(__("Add the smallest example that shows the problem.")) ?></li>
        <li><?= html(__("Tag it so the right people find it.")) ?></li>
      </ul>
    </div>
  </div>
<?php } ?>

</div>
