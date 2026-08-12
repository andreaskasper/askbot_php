<?php
/**
 * page_tags.php - browse all tags.
 */
i18n::init(__FILE__);

$sort = (string)($_GET["sort"] ?? "popular");
$query = (string)($_GET["q"] ?? "");
$result = Tag::all($sort, $query, (int)($_GET["page"] ?? 1), 36);

PageEngine::html("header", ["title" => __("Tags"), "description" => __("All topics on this site")]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><?= html(__("Tags")) ?></h1>
  <form class="d-flex gap-2" method="get">
    <input class="form-control form-control-sm" name="q" value="<?= htmlattr($query) ?>" placeholder="<?= htmlattr(__("Filter tags")) ?>">
    <div class="btn-group btn-group-sm">
      <?php foreach (["popular" => __("Popular"), "name" => __("Name"), "newest" => __("New")] as $key => $label) { ?>
        <a class="btn btn-outline-secondary <?= $sort === $key ? "active" : "" ?>" href="<?= htmlattr(Url::withParams(["sort" => $key, "page" => null])) ?>"><?= html($label) ?></a>
      <?php } ?>
    </div>
  </form>
</div>

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
  <?php foreach ($result["items"] as $tag) { ?>
    <div class="col">
      <div class="card h-100">
        <div class="card-body py-2">
          <a class="tag-chip" href="<?= htmlattr(url("questions?tag=" . rawurlencode($tag["name"]))) ?>"><?= html($tag["name"]) ?></a>
          <p class="small text-secondary mt-2 mb-1" style="min-height: 2.6rem">
            <?= html(Markdown::toText((string)$tag["description_md"], 90)) ?>
          </p>
          <div class="d-flex justify-content-between small text-secondary">
            <span><?= html(sprintf(__("%s questions"), i18n::shortNumber($tag["question_count"]))) ?></span>
            <a class="link-secondary" href="<?= htmlattr(url("tags/" . rawurlencode($tag["name"]) . "/edit")) ?>"><?= html(__("wiki")) ?></a>
          </div>
        </div>
      </div>
    </div>
  <?php } ?>
</div>
<?php PageEngine::html("box_pagination", ["page" => $result["page"], "pages" => $result["pages"]]); ?>
<?php PageEngine::html("footer"); ?>
