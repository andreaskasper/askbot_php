<?php
/**
 * box_profile_form.php - edit profile fields.
 *
 * @param array $params user
 */
$user = $params["user"];
$profile = [
    "id"           => $user->id(),
    "real_name"    => (string)$user->real_name,
    "website"      => (string)$user->website,
    "location"     => (string)$user->location,
    "country"      => (string)$user->country,
    "show_country" => (int)$user->show_country === 1,
    "bio"          => (string)$user->bio_md,
    "locale"       => (string)$user->locale,
    "email_digest" => (string)$user->email_digest,
    "email_on_answer"  => (int)$user->email_on_answer === 1,
    "email_on_comment" => (int)$user->email_on_comment === 1,
];
?>
<div id="profileApp" v-cloak>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label"><?= html(__("Real name")) ?></label>
      <input class="form-control" v-model="form.real_name" maxlength="120">
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= html(__("Website")) ?></label>
      <input class="form-control" v-model="form.website" maxlength="255" placeholder="https://">
    </div>
    <div class="col-md-6">
      <label class="form-label"><?= html(__("Location")) ?></label>
      <input class="form-control" v-model="form.location" maxlength="120">
    </div>
    <div class="col-md-3">
      <label class="form-label"><?= html(__("Country code")) ?></label>
      <input class="form-control" v-model="form.country" maxlength="2" placeholder="DE">
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="showCountry" v-model="form.show_country">
        <label class="form-check-label" for="showCountry"><?= html(__("show it")) ?></label>
      </div>
    </div>
    <div class="col-12">
      <label class="form-label"><?= html(__("About me")) ?></label>
      <textarea class="form-control" rows="6" v-model="form.bio"></textarea>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= html(__("Language")) ?></label>
      <select class="form-select" v-model="form.locale">
        <?php foreach (i18n::LANGUAGES as $code => $label) { ?>
          <option value="<?= htmlattr($code) ?>"><?= html($label) ?></option>
        <?php } ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label"><?= html(__("Email digest")) ?></label>
      <select class="form-select" v-model="form.email_digest">
        <option value="off"><?= html(__("never")) ?></option>
        <option value="daily"><?= html(__("daily")) ?></option>
        <option value="weekly"><?= html(__("weekly")) ?></option>
      </select>
    </div>
    <div class="col-md-4 d-flex align-items-end">
      <div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="mailAnswer" v-model="form.email_on_answer">
          <label class="form-check-label" for="mailAnswer"><?= html(__("mail me about answers")) ?></label></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="mailComment" v-model="form.email_on_comment">
          <label class="form-check-label" for="mailComment"><?= html(__("mail me about comments")) ?></label></div>
      </div>
    </div>
  </div>

  <button class="btn btn-primary mt-3" :disabled="busy" @click="save">
    <i v-if="busy" class="fa-solid fa-spinner fa-spin me-1"></i><?= html(__("Save profile")) ?>
  </button>
  <a class="btn btn-outline-secondary mt-3" href="<?= htmlattr(url("account/settings")) ?>"><?= html(__("Account settings")) ?></a>
</div>

<script>
const { createApp } = Vue;
createApp({
  data() { return { form: <?= json_encode($profile, JSON_UNESCAPED_UNICODE) ?>, busy: false }; },
  methods: {
    async save() {
      this.busy = true;
      try {
        await askbot.api("user.updateprofile", this.form);
        askbot.toast(askbot.i18n.saved);
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    }
  }
}).mount("#profileApp");
</script>
