<?php
/**
 * page_search.php
 */
i18n::init(__FILE__);

$query = trim((string)($_GET["q"] ?? ""));
$result = $query === ""
    ? ["items" => [], "answers" => [], "total" => 0, "page" => 1, "pages" => 1]
    : Search::run($query, ["page" => (int)($_GET["page"] ?? 1), "sort" => (string)($_GET["sort"] ?? "activity")]);
$authors = User::loadMany(array_column($result["items"], "author_id"));

PageEngine::html("header", ["title" => $query !== "" ? sprintf(__("Search: %s"), $query) : __("Search"), "noindex" => true]);
?>
<div class="row g-4">
  <div class="col-lg-9">
    <h1 class="h4 mb-3"><?= html(__("Search")) ?></h1>

    <form class="mb-3" method="get">
      <div class="input-group">
        <input class="form-control" name="q" value="<?= htmlattr($query) ?>" autofocus
               placeholder="<?= htmlattr(__("words, tag:php, user:12, answers:0, is:accepted, score:5")) ?>">
        <button class="btn btn-primary"><?= html(__("Search")) ?></button>
      </div>
    </form>

    <?php if ($query !== "") { ?>
      <p class="text-secondary small"><?= html(sprintf(__("%s results"), number_format($result["total"]))) ?></p>

      <?php if (!empty($result["answers"])) { ?>
        <div class="card mb-3">
          <div class="card-header py-2 small fw-semibold"><?= html(__("Matching answers")) ?></div>
          <ul class="list-group list-group-flush small">
            <?php foreach ($result["answers"] as $answer) { ?>
              <li class="list-group-item">
                <span class="badge text-bg-light float-end"><?= (int)$answer["score"] ?></span>
                <a href="<?= htmlattr(url("question/" . (int)$answer["question_id"] . "/" . $answer["slug"] . "#answer-" . (int)$answer["id"])) ?>"><?= html($answer["title"]) ?></a>
              </li>
            <?php } ?>
          </ul>
        </div>
      <?php } ?>

      <?php foreach ($result["items"] as $row) {
          PageEngine::html("box_question_summary", ["row" => $row, "author" => $authors[(int)$row["author_id"]] ?? null]);
      } ?>
      <?php if ($result["items"] === []) { ?>
        <div class="empty-state">
          <p><?= html(__("Nothing matched your search.")) ?></p>
          <a class="btn btn-primary" href="<?= htmlattr(url("questions/ask?title=" . rawurlencode($query))) ?>"><?= html(__("Ask this question")) ?></a>
        </div>
      <?php } ?>
      <?php PageEngine::html("box_pagination", ["page" => $result["page"], "pages" => $result["pages"]]); ?>
    <?php } ?>
  </div>
  <div class="col-lg-3"><?php PageEngine::html("box_sidebar", ["show" => ["tags", "help"]]); ?></div>
</div>
<?php PageEngine::html("footer"); ?>
