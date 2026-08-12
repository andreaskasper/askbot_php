<?php
i18n::init(__FILE__);
$stats = Statistics::overview();
PageEngine::html("header", ["title" => __("About"), "description" => (string)Config::get("site_description")]);
?>
<div class="row"><div class="col-lg-8">
  <h1 class="h4"><?= html(sprintf(__("About %s"), Config::get("site_title"))) ?></h1>
  <p class="lead"><?= html(Config::get("site_tagline")) ?></p>
  <p><?= html(Config::get("site_description")) ?></p>

  <h2 class="h6 mt-4"><?= html(__("How it works")) ?></h2>
  <ul>
    <li><?= html(__("Ask a question and describe what you already tried.")) ?></li>
    <li><?= html(__("Answers are voted on, so the useful ones rise to the top.")) ?></li>
    <li><?= html(__("The person who asked can mark one answer as accepted.")) ?></li>
    <li><?= html(__("Helpful contributions earn karma, which unlocks more of the site.")) ?></li>
  </ul>

  <h2 class="h6 mt-4"><?= html(__("In numbers")) ?></h2>
  <div class="row text-center g-3">
    <div class="col-4"><div class="fs-4 fw-semibold"><?= html(i18n::shortNumber($stats["questions"])) ?></div><div class="small text-secondary"><?= html(__("questions")) ?></div></div>
    <div class="col-4"><div class="fs-4 fw-semibold"><?= html(i18n::shortNumber($stats["answers"])) ?></div><div class="small text-secondary"><?= html(__("answers")) ?></div></div>
    <div class="col-4"><div class="fs-4 fw-semibold"><?= html(i18n::shortNumber($stats["users"])) ?></div><div class="small text-secondary"><?= html(__("members")) ?></div></div>
  </div>
</div></div>
<?php PageEngine::html("footer"); ?>
