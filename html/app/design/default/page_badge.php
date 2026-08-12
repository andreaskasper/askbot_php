<?php
/**
 * page_badge.php - one badge and who earned it.
 */
i18n::init(__FILE__);

$badge = Badge::one((int)$params["id"]);
if ($badge === []) PageEngine::error(404);

PageEngine::html("header", ["title" => (string)$badge["name"], "description" => (string)$badge["description"]]);
?>
<h1 class="h4 <?= htmlattr(Badge::levelClass((string)$badge["level"])) ?>"><span class="badge-dot"></span><?= html($badge["name"]) ?></h1>
<p class="text-secondary"><?= html($badge["description"]) ?></p>
<p class="small text-secondary"><?= html(sprintf(__("awarded %s times"), i18n::shortNumber($badge["awarded_count"]))) ?></p>

<div class="row row-cols-1 row-cols-md-3 g-2 mt-2">
  <?php foreach (Badge::recipients((int)$badge["id"], 90) as $row) { ?>
    <div class="col small">
      <a href="<?= htmlattr(url("users/" . (int)$row["id"] . "/" . $row["slug"])) ?>"><?= html($row["username"]) ?></a>
      <span class="badge text-bg-light"><?= html(i18n::shortNumber($row["karma"])) ?></span>
      <span class="text-secondary"><?= html(i18n::ago((string)$row["awarded_at"])) ?></span>
    </div>
  <?php } ?>
</div>
<?php PageEngine::html("footer"); ?>
