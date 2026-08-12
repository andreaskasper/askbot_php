<?php
/**
 * page_recover.php - request a password reset link.
 */
i18n::init(__FILE__);

$done = false;
$error = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    Csrf::check();
    try {
        Auth::sendPasswordReset((string)($_POST["email"] ?? ""));
        $done = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

PageEngine::html("header", ["title" => __("Reset your password"), "noindex" => true]);
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h1 class="h4 mb-3"><?= html(__("Reset your password")) ?></h1>
    <?php if ($done) { ?>
      <div class="alert alert-success"><?= html(__("If we know that address, a reset link is on its way.")) ?></div>
      <a class="btn btn-outline-secondary" href="<?= htmlattr(url("account/signin")) ?>"><?= html(__("Back to sign in")) ?></a>
    <?php } else { ?>
      <?php if ($error !== "") { ?><div class="alert alert-danger"><?= html($error) ?></div><?php } ?>
      <form method="post" class="card card-body">
        <?= Csrf::field() ?>
        <label class="form-label"><?= html(__("Your email address")) ?></label>
        <input class="form-control" type="email" name="email" required autofocus>
        <button class="btn btn-primary mt-3"><?= html(__("Send reset link")) ?></button>
      </form>
    <?php } ?>
  </div>
</div>
<?php PageEngine::html("footer"); ?>
