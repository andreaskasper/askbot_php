<?php
/**
 * page_token.php - email verification and password reset links land here.
 *
 * @param array $params action (verify|reset), token
 */
i18n::init(__FILE__);

$action = (string)$params["action"];
$token = (string)$params["token"];
$error = "";
$done = false;

if ($action === "verify") {
    $user = Auth::useToken($token, "verify_email");
    if ($user === null) {
        $error = __("This link is no longer valid. Please request a new one.");
    } else {
        $user->save(["email_verified_at" => gmdate("Y-m-d H:i:s")]);
        MyUser::login($user);
        PageEngine::AddSuccessMessage(__("Your email address is confirmed. Welcome!"));
        PageEngine::goto(url("/"));
    }
} else {
    if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
        Csrf::check();
        try {
            $user = Auth::useToken($token, "reset_password");
            if ($user === null) throw new \RuntimeException(__("This link is no longer valid. Please request a new one."));
            $password = (string)($_POST["password"] ?? "");
            if ($password !== (string)($_POST["password_repeat"] ?? "")) throw new \InvalidArgumentException(__("The two passwords do not match."));
            Auth::checkPasswordStrength($password);
            $user->setPassword($password);
            Audit::log("password.reset", "user:" . $user->id(), [], $user->id());
            $done = true;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

PageEngine::html("header", ["title" => __("Set a new password"), "noindex" => true]);
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h1 class="h4 mb-3"><?= html($action === "verify" ? __("Confirm your email") : __("Set a new password")) ?></h1>
    <?php if ($error !== "") { ?><div class="alert alert-danger"><?= html($error) ?></div><?php } ?>
    <?php if ($done) { ?>
      <div class="alert alert-success"><?= html(__("Your password was changed. You can sign in now.")) ?></div>
      <a class="btn btn-primary" href="<?= htmlattr(url("account/signin")) ?>"><?= html(__("Sign in")) ?></a>
    <?php } elseif ($action === "reset" && $error === "") { ?>
      <form method="post" class="card card-body">
        <?= Csrf::field() ?>
        <div class="mb-3">
          <label class="form-label"><?= html(__("New password")) ?></label>
          <input class="form-control" type="password" name="password" minlength="10" autocomplete="new-password" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= html(__("Repeat password")) ?></label>
          <input class="form-control" type="password" name="password_repeat" autocomplete="new-password" required>
        </div>
        <button class="btn btn-primary w-100"><?= html(__("Save password")) ?></button>
      </form>
    <?php } ?>
  </div>
</div>
<?php PageEngine::html("footer"); ?>
