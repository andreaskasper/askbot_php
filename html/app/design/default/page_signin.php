<?php
/**
 * page_signin.php - sign in, including the second factor step.
 */
i18n::init(__FILE__);

if (MyUser::isLoggedIn()) PageEngine::goto(url("/"));

$error = "";
$needsTwoFactor = !empty($_SESSION["pending_2fa_user"]);

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    Csrf::check();
    try {
        if (($_POST["action"] ?? "") === "2fa") {
            if (Auth::completeTwoFactor((string)($_POST["code"] ?? ""), !empty($_POST["remember"]))) {
                PageEngine::goto(Url::safeReturn($_SESSION["return_to"] ?? null));
            }
            $error = __("That code is not valid.");
        } else {
            $result = Auth::attempt((string)($_POST["login"] ?? ""), (string)($_POST["password"] ?? ""));
            switch ($result["status"]) {
                case "ok":
                    MyUser::login($result["user"], !empty($_POST["remember"]));
                    PageEngine::goto(Url::safeReturn($_SESSION["return_to"] ?? null));
                    break;
                case "2fa":
                    $needsTwoFactor = true;
                    break;
                case "suspended":
                    $error = __("This account is suspended.");
                    break;
                case "unverified":
                    Auth::sendVerificationMail($result["user"]);
                    $error = __("Please confirm your email address first - we sent you a new link.");
                    break;
                default:
                    $error = __("Wrong user name or password.");
            }
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

PageEngine::html("header", ["title" => __("Sign in"), "noindex" => true]);
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <h1 class="h4 mb-3"><?= html(__("Sign in")) ?></h1>

    <?php if ($error !== "") { ?><div class="alert alert-danger"><?= html($error) ?></div><?php } ?>

    <?php if ($needsTwoFactor) { ?>
      <form method="post" class="card card-body">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="2fa">
        <p class="text-secondary small"><?= html(__("Enter the six digit code from your authenticator app.")) ?></p>
        <input class="form-control form-control-lg text-center" name="code" inputmode="numeric" autocomplete="one-time-code"
               maxlength="6" pattern="[0-9]{6}" autofocus required>
        <button class="btn btn-primary mt-3"><?= html(__("Continue")) ?></button>
      </form>
    <?php } else { ?>
      <form method="post" class="card card-body">
        <?= Csrf::field() ?>
        <div class="mb-3">
          <label class="form-label"><?= html(__("User name or email")) ?></label>
          <input class="form-control" name="login" autocomplete="username" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label"><?= html(__("Password")) ?></label>
          <input class="form-control" type="password" name="password" autocomplete="current-password" required>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
          <label class="form-check-label" for="remember"><?= html(__("Keep me signed in")) ?></label>
        </div>
        <button class="btn btn-primary w-100"><?= html(__("Sign in")) ?></button>
      </form>

      <?php $providers = OAuth::available(); if ($providers !== []) { ?>
        <div class="text-center text-secondary my-3 small"><?= html(__("or")) ?></div>
        <?php foreach ($providers as $key => $provider) { ?>
          <a class="btn btn-outline-secondary w-100 mb-2" href="<?= htmlattr(url("account/oauth/" . $key)) ?>">
            <i class="<?= htmlattr($provider["icon"]) ?> me-2"></i><?= html(sprintf(__("Continue with %s"), $provider["name"])) ?>
          </a>
        <?php } ?>
      <?php } ?>

      <div class="d-flex justify-content-between mt-3 small">
        <a href="<?= htmlattr(url("account/recover")) ?>"><?= html(__("Forgot your password?")) ?></a>
        <a href="<?= htmlattr(url("account/signup")) ?>"><?= html(__("Create an account")) ?></a>
      </div>
    <?php } ?>
  </div>
</div>
<?php PageEngine::html("footer"); ?>
