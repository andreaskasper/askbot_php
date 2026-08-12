<?php
/**
 * page_answer_edit.php - edit one answer.
 */
i18n::init(__FILE__);

$answer = new Answer((int)$params["id"]);
if (!$answer->exists()) PageEngine::error(404);
if (!MyUser::can("edit", $answer->row())) PageEngine::error(403, __("You are not allowed to edit this answer."));

$question = new Question((int)$answer->question_id);

PageEngine::html("header", ["title" => __("Edit answer"), "noindex" => true]);
?>
<div id="editAnswerApp" v-cloak class="row g-4">
  <div class="col-lg-8">
    <h1 class="h4"><?= html(__("Edit answer")) ?></h1>
    <p class="text-secondary">
      <?= html(__("Answer to")) ?>
      <a href="<?= htmlattr($question->url()) ?>"><?= html($question->title) ?></a>
    </p>

    <?php PageEngine::html("box_editor", ["model" => "body", "rows" => 16]); ?>

    <div class="my-3">
      <label class="form-label"><?= html(__("What did you change?")) ?></label>
      <input class="form-control" v-model="comment" maxlength="255">
    </div>

    <button class="btn btn-primary" :disabled="busy || body.trim().length < 20" @click="submit">
      <i v-if="busy" class="fa-solid fa-spinner fa-spin me-1"></i><?= html(__("Save changes")) ?>
    </button>
    <a class="btn btn-outline-secondary" href="<?= htmlattr($question->url()) ?>"><?= html(__("Cancel")) ?></a>
  </div>
</div>

<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      id: <?= (int)$answer->id() ?>,
      body: <?= json_encode((string)$answer->body_md) ?>,
      comment: "",
      preview: "",
      showPreview: false,
      previewTimer: null,
      busy: false
    };
  },
  methods: {
    schedulePreview() { clearTimeout(this.previewTimer); this.previewTimer = setTimeout(this.renderPreview, 400); },
    async renderPreview() {
      if (!this.showPreview || this.body.trim() === "") { this.preview = ""; return; }
      try { this.preview = (await askbot.api("site.preview", { body: this.body })).html; } catch (e) {}
    },
    insertMarkup(before, after) {
      const editor = this.$refs.editor;
      const start = editor.selectionStart, end = editor.selectionEnd;
      const selected = this.body.substring(start, end);
      this.body = this.body.substring(0, start) + before + selected + after + this.body.substring(end);
      this.$nextTick(() => { editor.focus(); editor.selectionStart = start + before.length; editor.selectionEnd = start + before.length + selected.length; });
      this.schedulePreview();
    },
    async uploadImage(event) {
      const file = event.target.files[0];
      if (!file) return;
      const body = new FormData();
      body.append("file", file); body.append("csrf_token", askbot.csrfToken);
      try {
        const response = await fetch(askbot.baseUrl + "/api/site.upload.json", { method: "POST", body });
        const data = await response.json();
        if (data.err.id !== 0) throw new Error(data.err.msg);
        this.insertMarkup("![](" + data.result.url + ")", "");
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { event.target.value = ""; }
    },
    async submit() {
      this.busy = true;
      try {
        const result = await askbot.api("answer.update", { id: this.id, body: this.body, comment: this.comment });
        location.href = result.url;
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    }
  },
  watch: { showPreview(value) { if (value) this.renderPreview(); } }
}).mount("#editAnswerApp");
</script>
<?php PageEngine::html("footer"); ?>
