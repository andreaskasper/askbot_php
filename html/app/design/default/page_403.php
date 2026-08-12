<?php
i18n::init(__FILE__);
if (!headers_sent()) http_response_code(403);
PageEngine::html("header", ["title" => __("Not allowed"), "noindex" => true]);
?>
<div class="empty-state">
  <div class="display-1 fw-light">403</div>
  <p class="lead"><?= html($params["message"] ?? __("You are not allowed to do that.")) ?></p>
  <?php if (!MyUser::isLoggedIn()) { ?>
    <a class="btn btn-primary" href="<?= htmlattr(url("account/signin")) ?>"><?= html(__("Sign in")) ?></a>
  <?php } else { ?>
    <a class="btn btn-primary" href="<?= htmlattr(url("/")) ?>"><?= html(__("Back to the questions")) ?></a>
  <?php } ?>
</div>
<?php PageEngine::html("footer"); ?>
