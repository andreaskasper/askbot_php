<?php
/**
 * page_signup.php - registration.
 */
i18n::init(__FILE__);

if (MyUser::isLoggedIn()) PageEngine::goto(url("/"));

$error = "";
$done = false;

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    Csrf::check();
    try {
        // Simple honeypot: bots fill in every field they can find.
        if (trim((string)($_POST["website"] ?? "")) !== "") throw new \RuntimeException(__("Something went wrong. Please try again."));

        $user = Auth::register(
            (string)($_POST["username"] ?? ""),
            (string)($_POST["email"] ?? ""),
            (string)($_POST["password"] ?? ""),
            (string)($_POST["password_repeat"] ?? "")
        );
        if ($user->email_verified_at !== null) {
            MyUser::login($user);
            PageEngine::AddSuccessMessage(__("Welcome! Your account is ready."));
            PageEngine::goto(url("/"));
        }
        $done = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

PageEngine::html("header", ["title" => __("Sign up"), "noindex" => true]);
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h1 class="h4 mb-3"><?= html(__("Create an account")) ?></h1>

    <?php if ($done) { ?>
      <div class="alert alert-success">
        <p class="mb-0"><?= html(__("Almost there - we sent you an email. Click the link in it to activate your account.")) ?></p>
      </div>
    <?php } else { ?>
      <?php if ($error !== "") { ?><div class="alert alert-danger"><?= html($error) ?></div><?php } ?>
      <form method="post" class="card card-body">
        <?= Csrf::field() ?>
        <div class="mb-3">
          <label class="form-label"><?= html(__("User name")) ?></label>
          <input class="form-control" name="username" value="<?= htmlattr($_POST["username"] ?? "") ?>" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= html(__("Email")) ?></label>
          <input class="form-control" type="email" name="email" value="<?= htmlattr($_POST["email"] ?? "") ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= html(__("Password")) ?></label>
          <input class="form-control" type="password" name="password" autocomplete="new-password" minlength="10" required>
          <div class="form-text"><?= html(__("At least 10 characters.")) ?></div>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= html(__("Repeat password")) ?></label>
          <input class="form-control" type="password" name="password_repeat" autocomplete="new-password" required>
        </div>
        <div class="d-none" aria-hidden="true">
          <label>Website</label><input name="website" tabindex="-1" autocomplete="off">
        </div>
        <button class="btn btn-primary w-100"><?= html(__("Sign up")) ?></button>
        <p class="small text-secondary mt-3 mb-0">
          <?= html(__("By signing up you agree to the")) ?>
          <a href="<?= htmlattr(url("terms")) ?>"><?= html(__("terms")) ?></a>
          <?= html(__("and the")) ?>
          <a href="<?= htmlattr(url("privacy")) ?>"><?= html(__("privacy policy")) ?></a>.
        </p>
      </form>

      <?php $providers = OAuth::available(); if ($providers !== []) { ?>
        <div class="text-center text-secondary my-3 small"><?= html(__("or")) ?></div>
        <?php foreach ($providers as $key => $provider) { ?>
          <a class="btn btn-outline-secondary w-100 mb-2" href="<?= htmlattr(url("account/oauth/" . $key)) ?>">
            <i class="<?= htmlattr($provider["icon"]) ?> me-2"></i><?= html(sprintf(__("Continue with %s"), $provider["name"])) ?>
          </a>
        <?php } ?>
      <?php } ?>
    <?php } ?>
  </div>
</div>
<?php PageEngine::html("footer"); ?>
