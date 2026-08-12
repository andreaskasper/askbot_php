<?php
/**
 * page_settings.php - account settings: password, two factor, API keys.
 */
i18n::init(__FILE__);

$user = MyUser::user();
$secret = null;
$error = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    Csrf::check();
    try {
        switch ((string)($_POST["action"] ?? "")) {
            case "2fa_start":
                $secret = Totp::generateSecret();
                $_SESSION["totp_candidate"] = $secret;
                break;

            case "2fa_confirm":
                $candidate = (string)($_SESSION["totp_candidate"] ?? "");
                if ($candidate === "" || !Totp::verify($candidate, (string)($_POST["code"] ?? ""))) {
                    throw new \RuntimeException(__("That code is not valid. Please try again."));
                }
                $user->save(["totp_secret" => $candidate, "totp_enabled" => 1]);
                unset($_SESSION["totp_candidate"]);
                Audit::log("2fa.enabled", "user:" . $user->id());
                PageEngine::AddSuccessMessage(__("Two factor authentication is on."));
                PageEngine::goto(url("account/settings"));
                break;

            case "2fa_disable":
                $user->save(["totp_secret" => null, "totp_enabled" => 0]);
                Audit::log("2fa.disabled", "user:" . $user->id());
                PageEngine::AddSuccessMessage(__("Two factor authentication is off."));
                PageEngine::goto(url("account/settings"));
                break;
        }
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$db = new SQL(0);
$apiKeys = $db->cmdrows('SELECT id, label, created_at FROM user_tokens WHERE user_id = "{0}" AND type = "api" ORDER BY id DESC', [$user->id()]);

PageEngine::html("header", ["title" => __("Account settings"), "noindex" => true]);
?>
<div id="settingsApp" v-cloak class="row g-4">
  <div class="col-lg-8">
    <h1 class="h4 mb-3"><?= html(__("Account settings")) ?></h1>
    <?php if ($error !== "") { ?><div class="alert alert-danger"><?= html($error) ?></div><?php } ?>

    <!-- password -->
    <div class="card mb-4">
      <div class="card-header py-2 fw-semibold"><?= html(__("Password")) ?></div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-md-4"><input class="form-control" type="password" v-model="currentPassword" placeholder="<?= htmlattr(__("current password")) ?>"></div>
          <div class="col-md-4"><input class="form-control" type="password" v-model="newPassword" placeholder="<?= htmlattr(__("new password")) ?>"></div>
          <div class="col-md-4"><input class="form-control" type="password" v-model="repeatPassword" placeholder="<?= htmlattr(__("repeat")) ?>"></div>
        </div>
        <button class="btn btn-primary btn-sm mt-3" :disabled="busy || newPassword.length < 10" @click="changePassword"><?= html(__("Change password")) ?></button>
      </div>
    </div>

    <!-- two factor -->
    <div class="card mb-4">
      <div class="card-header py-2 fw-semibold"><?= html(__("Two factor authentication")) ?></div>
      <div class="card-body">
        <?php if ((int)$user->totp_enabled === 1) { ?>
          <p class="text-success mb-2"><i class="fa-solid fa-shield-halved me-1"></i><?= html(__("Enabled.")) ?></p>
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="2fa_disable">
            <button class="btn btn-outline-danger btn-sm"><?= html(__("Turn off")) ?></button>
          </form>
        <?php } elseif ($secret !== null) { ?>
          <p class="small"><?= html(__("Scan this in your authenticator app, then enter the code it shows.")) ?></p>
          <p><code><?= html($secret) ?></code></p>
          <p class="small text-secondary"><?= html(__("Or open this link on the phone:")) ?><br>
            <code style="word-break: break-all"><?= html(Totp::provisioningUri($secret, (string)$user->email, (string)Config::get("site_title"))) ?></code>
          </p>
          <form method="post" class="d-flex gap-2" style="max-width: 20rem">
            <?= Csrf::field() ?><input type="hidden" name="action" value="2fa_confirm">
            <input class="form-control" name="code" inputmode="numeric" maxlength="6" placeholder="123456" required>
            <button class="btn btn-primary"><?= html(__("Confirm")) ?></button>
          </form>
        <?php } else { ?>
          <p class="small text-secondary"><?= html(__("Protect your account with a second factor from an authenticator app.")) ?></p>
          <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="2fa_start">
            <button class="btn btn-outline-primary btn-sm"><?= html(__("Set it up")) ?></button>
          </form>
        <?php } ?>
      </div>
    </div>

    <!-- api keys -->
    <div class="card mb-4">
      <div class="card-header py-2 fw-semibold"><?= html(__("API keys")) ?></div>
      <div class="card-body">
        <p class="small text-secondary"><?= html(__("Send the key as an Authorization: Bearer header to use the API without a session.")) ?></p>
        <?php if ($apiKeys !== []) { ?>
          <ul class="list-group list-group-flush mb-3">
            <?php foreach ($apiKeys as $key) { ?>
              <li class="list-group-item px-0 small d-flex justify-content-between">
                <span><?= html($key["label"] ?: __("API key")) ?></span>
                <span class="text-secondary"><?= html(i18n::date((string)$key["created_at"], "Y-m-d")) ?></span>
              </li>
            <?php } ?>
          </ul>
        <?php } ?>
        <div class="input-group input-group-sm" style="max-width: 26rem">
          <input class="form-control" v-model="keyLabel" placeholder="<?= htmlattr(__("what is it for?")) ?>">
          <button class="btn btn-outline-primary" :disabled="busy" @click="createKey"><?= html(__("Create key")) ?></button>
        </div>
        <div v-if="newKey" class="alert alert-warning mt-3 mb-0">
          <strong><?= html(__("Copy it now, it is not shown again:")) ?></strong>
          <code style="word-break: break-all">{{ newKey }}</code>
        </div>
      </div>
    </div>

    <p><a href="<?= htmlattr(url("users/" . MyUser::id() . "/" . $user->slug . "/edit")) ?>"><?= html(__("Edit your public profile")) ?></a></p>
  </div>
</div>

<script>
const { createApp } = Vue;
createApp({
  data() { return { currentPassword: "", newPassword: "", repeatPassword: "", keyLabel: "", newKey: "", busy: false }; },
  methods: {
    async changePassword() {
      this.busy = true;
      try {
        await askbot.api("user.setpassword", {
          current_password: this.currentPassword,
          password: this.newPassword,
          password_repeat: this.repeatPassword
        });
        this.currentPassword = this.newPassword = this.repeatPassword = "";
        askbot.toast(askbot.i18n.saved);
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    },
    async createKey() {
      this.busy = true;
      try {
        const result = await askbot.api("user.createapikey", { label: this.keyLabel });
        this.newKey = result.api_key;
        this.keyLabel = "";
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    }
  }
}).mount("#settingsApp");
</script>
<?php PageEngine::html("footer"); ?>
