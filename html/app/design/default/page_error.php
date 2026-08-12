<?php
i18n::init(__FILE__);
$code = (int)($params["code"] ?? 500);
$message = (string)($params["message"] ?? "");
if (!headers_sent()) http_response_code($code);
PageEngine::html("header", ["title" => $code . " - " . ($message !== "" ? $message : __("Error")), "noindex" => true]);
?>
<div class="empty-state">
  <div class="display-1 fw-light"><?= (int)$code ?></div>
  <p class="lead"><?= html($message !== "" ? $message : __("Something went wrong.")) ?></p>
  <a class="btn btn-primary" href="<?= htmlattr(url("/")) ?>"><?= html(__("Back to the questions")) ?></a>
</div>
<?php PageEngine::html("footer"); ?>
