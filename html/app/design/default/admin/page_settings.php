<?php
/**
 * admin/page_settings.php - every setting in one form.
 */
i18n::init(__FILE__);

$groups = [
    __("Site") => ["site_title", "site_tagline", "site_description", "site_language", "site_theme"],
    __("Content") => ["questions_per_page", "answers_per_page", "min_title_length", "min_question_length", "min_answer_length", "min_tags_per_question", "max_tags_per_question", "feed_item_count"],
    __("Accounts") => ["registration_open", "require_email_verification", "allow_anonymous_read"],
    __("Karma") => ["karma_new_user", "karma_question_upvote", "karma_question_downvote", "karma_answer_upvote", "karma_answer_downvote", "karma_downvote_cost", "karma_answer_accepted", "karma_accept_answer", "karma_daily_cap"],
    __("Thresholds") => ["threshold_comment", "threshold_vote_up", "threshold_vote_down", "threshold_flag", "threshold_edit_wiki", "threshold_close_vote", "threshold_tag_wiki", "threshold_edit_others", "threshold_delete_vote"],
    __("Moderation") => ["close_votes_needed", "flags_needed_autohide"],
];

PageEngine::html("header", ["title" => __("Administration"), "noindex" => true]);
PageEngine::html("admin/box_nav", ["active" => "settings"]);
?>
<div id="adminApp" v-cloak>
  <h1 class="h4 mb-3"><?= html(__("Settings")) ?></h1>

  <?php foreach ($groups as $group => $keys) { ?>
    <div class="card mb-3">
      <div class="card-header py-2 fw-semibold"><?= html($group) ?></div>
      <div class="card-body">
        <div class="row g-3">
          <?php foreach ($keys as $key) {
              $value = Config::get($key);
              $isBool = in_array($key, ["registration_open", "require_email_verification", "allow_anonymous_read"], true);
              $isLong = in_array($key, ["site_description", "site_tagline"], true);
          ?>
            <div class="col-md-<?= $isLong ? 12 : 4 ?>">
              <label class="form-label small text-secondary"><?= html($key) ?></label>
              <?php if ($isBool) { ?>
                <select class="form-select form-select-sm" v-model="settings['<?= htmlattr($key) ?>']">
                  <option value="1"><?= html(__("yes")) ?></option>
                  <option value="0"><?= html(__("no")) ?></option>
                </select>
              <?php } elseif ($key === "site_language") { ?>
                <select class="form-select form-select-sm" v-model="settings['<?= htmlattr($key) ?>']">
                  <?php foreach (i18n::LANGUAGES as $code => $label) { ?>
                    <option value="<?= htmlattr($code) ?>"><?= html($label) ?></option>
                  <?php } ?>
                </select>
              <?php } elseif ($key === "site_theme") { ?>
                <select class="form-select form-select-sm" v-model="settings['<?= htmlattr($key) ?>']">
                  <option value="auto"><?= html(__("follow the browser")) ?></option>
                  <option value="light"><?= html(__("light")) ?></option>
                  <option value="dark"><?= html(__("dark")) ?></option>
                </select>
              <?php } elseif ($isLong) { ?>
                <input class="form-control form-control-sm" v-model="settings['<?= htmlattr($key) ?>']">
              <?php } else { ?>
                <input class="form-control form-control-sm" v-model="settings['<?= htmlattr($key) ?>']">
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </div>
    </div>
  <?php } ?>

  <button class="btn btn-primary" :disabled="busy" @click="save">
    <i v-if="busy" class="fa-solid fa-spinner fa-spin me-1"></i><?= html(__("Save settings")) ?>
  </button>
</div>

<script>
const { createApp } = Vue;
createApp({
  data() { return { settings: <?= json_encode(array_map("strval", Config::all()), JSON_UNESCAPED_UNICODE) ?>, busy: false }; },
  methods: {
    async save() {
      this.busy = true;
      try {
        await askbot.api("admin.setsettings", this.settings);
        askbot.toast(askbot.i18n.saved);
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    }
  }
}).mount("#adminApp");
</script>
<?php PageEngine::html("footer"); ?>
