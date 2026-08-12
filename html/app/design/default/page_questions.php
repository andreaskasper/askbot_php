<?php
/**
 * page_questions.php - the question list, also used for tag filters.
 */
i18n::init(__FILE__);

$filter = [
    "tag"   => (string)($_GET["tag"] ?? ""),
    "q"     => (string)($_GET["q"] ?? ""),
    "scope" => (string)($_GET["scope"] ?? ""),
    "sort"  => (string)($_GET["sort"] ?? "activity"),
    "page"  => (int)($_GET["page"] ?? 1),
];
$result = Question::search($filter);
$authors = User::loadMany(array_column($result["items"], "author_id"));

$tagRow = $filter["tag"] !== "" ? Tag::byName($filter["tag"]) : [];
$title = $filter["tag"] !== ""
    ? sprintf(__("Questions tagged [%s]"), $filter["tag"])
    : ($filter["scope"] === "unanswered" ? __("Unanswered questions") : __("All questions"));

PageEngine::html("header", [
    "title"       => $title,
    "description" => $filter["tag"] !== "" ? sprintf(__("Questions about %s"), $filter["tag"]) : (string)Config::get("site_description"),
    "canonical"   => url("questions" . ($filter["tag"] !== "" ? "?tag=" . rawurlencode($filter["tag"]) : "")),
]);

$sorts = [
    "activity" => __("Active"),
    "newest"   => __("Newest"),
    "votes"    => __("Votes"),
    "answers"  => __("Answers"),
    "hot"      => __("Hot"),
];
$scopes = [
    ""           => __("All"),
    "unanswered" => __("Unanswered"),
    "unsolved"   => __("No accepted answer"),
    "bounty"     => __("Bounty"),
];
?>
<div class="row g-4">
  <div class="col-lg-9">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
      <h1 class="h4 mb-0"><?= html($title) ?></h1>
      <a class="btn btn-primary btn-sm d-lg-none" href="<?= htmlattr(url("questions/ask")) ?>"><?= html(__("Ask a question")) ?></a>
    </div>

    <?php if ($tagRow !== [] && ($tagRow["description_html"] ?? "") !== "") { ?>
      <div class="alert alert-light border">
        <div class="post-body small"><?= $tagRow["description_html"] ?></div>
        <a class="small" href="<?= htmlattr(url("tags/" . rawurlencode((string)$tagRow["name"]) . "/edit")) ?>"><?= html(__("edit tag wiki")) ?></a>
      </div>
    <?php } ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom pb-2 mb-2">
      <span class="text-secondary small"><?= html(sprintf(__("%s questions"), number_format($result["total"]))) ?></span>
      <div class="d-flex gap-2">
        <div class="btn-group btn-group-sm" role="group">
          <?php foreach ($sorts as $key => $label) { ?>
            <a class="btn btn-outline-secondary <?= $filter["sort"] === $key ? "active" : "" ?>"
               href="<?= htmlattr(Url::withParams(["sort" => $key, "page" => null])) ?>"><?= html($label) ?></a>
          <?php } ?>
        </div>
        <select class="form-select form-select-sm w-auto" onchange="location.href=this.value">
          <?php foreach ($scopes as $key => $label) { ?>
            <option value="<?= htmlattr(Url::withParams(["scope" => $key === "" ? null : $key, "page" => null])) ?>" <?= $filter["scope"] === $key ? "selected" : "" ?>>
              <?= html($label) ?>
            </option>
          <?php } ?>
        </select>
      </div>
    </div>

    <?php if ($result["items"] === []) { ?>
      <div class="empty-state">
        <i class="fa-regular fa-face-smile fa-2x mb-3 d-block"></i>
        <p><?= html(__("No questions here yet.")) ?></p>
        <a class="btn btn-primary" href="<?= htmlattr(url("questions/ask")) ?>"><?= html(__("Ask the first question")) ?></a>
      </div>
    <?php } else { ?>
      <?php foreach ($result["items"] as $row) {
          PageEngine::html("box_question_summary", ["row" => $row, "author" => $authors[(int)$row["author_id"]] ?? null]);
      } ?>
      <?php PageEngine::html("box_pagination", ["page" => $result["page"], "pages" => $result["pages"]]); ?>
    <?php } ?>

  </div>
  <div class="col-lg-3">
    <?php PageEngine::html("box_sidebar", ["show" => ["ask", "stats", "tags", "help"]]); ?>
  </div>
</div>
<?php PageEngine::html("footer"); ?>
