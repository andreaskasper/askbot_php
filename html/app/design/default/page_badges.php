<?php
/**
 * page_badges.php
 */
i18n::init(__FILE__);

$badges = Badge::all();
PageEngine::html("header", ["title" => __("Badges"), "description" => __("What you can earn on this site")]);
?>
<h1 class="h4 mb-3"><?= html(__("Badges")) ?></h1>
<p class="text-secondary"><?= html(__("Badges recognise helpful behaviour. They are awarded automatically.")) ?></p>

<?php foreach (["gold" => __("Gold"), "silver" => __("Silver"), "bronze" => __("Bronze")] as $level => $label) { ?>
  <h2 class="h6 mt-4 <?= htmlattr(Badge::levelClass($level)) ?>"><span class="badge-dot"></span><?= html($label) ?></h2>
  <div class="row row-cols-1 row-cols-md-3 g-3">
    <?php foreach ($badges as $badge) { if ($badge["level"] !== $level) continue; ?>
      <div class="col"><div class="card h-100"><div class="card-body py-2">
        <a class="fw-semibold text-decoration-none <?= htmlattr(Badge::levelClass($level)) ?>"
           href="<?= htmlattr(url("badges/" . (int)$badge["id"] . "/" . Slug::make((string)$badge["name"]))) ?>">
          <span class="badge-dot"></span><?= html($badge["name"]) ?>
        </a>
        <div class="small text-secondary"><?= html($badge["description"]) ?></div>
        <div class="small text-secondary mt-1"><?= html(sprintf(__("awarded %s times"), i18n::shortNumber($badge["awarded_count"]))) ?></div>
      </div></div></div>
    <?php } ?>
  </div>
<?php } ?>
<?php PageEngine::html("footer"); ?>
