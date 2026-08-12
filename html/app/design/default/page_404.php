<?php
i18n::init(__FILE__);
if (!headers_sent()) http_response_code(404);
PageEngine::html("header", ["title" => __("Page not found"), "noindex" => true]);
$query = trim((string)(\web\Routing::$path ?? ""), "/");
$suggestions = $query !== "" ? Search::suggest(str_replace(["-", "/"], " ", $query), 5) : [];
?>
<div class="empty-state">
  <div class="display-1 fw-light">404</div>
  <p class="lead"><?= html(__("We could not find that page.")) ?></p>
  <?php if ($suggestions !== []) { ?>
    <p><?= html(__("Did you mean one of these?")) ?></p>
    <ul class="list-unstyled">
      <?php foreach ($suggestions as $item) { ?>
        <li><a href="<?= htmlattr(url("question/" . (int)$item["id"] . "/" . $item["slug"])) ?>"><?= html($item["title"]) ?></a></li>
      <?php } ?>
    </ul>
  <?php } ?>
  <a class="btn btn-primary" href="<?= htmlattr(url("/")) ?>"><?= html(__("Back to the questions")) ?></a>
</div>
<?php PageEngine::html("footer"); ?>
