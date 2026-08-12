<?php
/**
 * page_ask.php - the ask form.
 *
 * @param array $params id (question id when editing), mode ("ask"|"edit")
 */
i18n::init(__FILE__);

$mode = $params["mode"] ?? "ask";
$question = null;
if ($mode === "edit") {
    $question = new Question((int)$params["id"]);
    if (!$question->exists()) PageEngine::error(404);
    if (!MyUser::can("edit", $question->row())) PageEngine::error(403, __("You are not allowed to edit this question."));
}

$config = [
    "mode"      => $mode,
    "id"        => $question?->id() ?? 0,
    "title"     => $question ? (string)$question->title : (string)($_GET["title"] ?? ""),
    "body"      => $question ? (string)$question->body_md : "",
    "tags"      => $question ? $question->tagList() : array_values(array_filter([(string)($_GET["tag"] ?? "")])),
    "maxTags"   => Config::int("max_tags_per_question", 5),
    "minTitle"  => Config::int("min_title_length", 15),
    "minBody"   => Config::int("min_question_length", 20),
];

PageEngine::html("header", [
    "title"   => $mode === "edit" ? __("Edit question") : __("Ask a question"),
    "noindex" => true,
]);
?>

<div id="askApp" v-cloak>
<div class="row g-4">
  <div class="col-lg-8">
    <h1 class="h4 mb-3">{{ mode === 'edit' ? <?= json_encode(__("Edit question")) ?> : <?= json_encode(__("Ask a question")) ?> }}</h1>

    <div class="mb-3">
      <label class="form-label fw-semibold"><?= html(__("Title")) ?></label>
      <input class="form-control" v-model="title" maxlength="300" @input="scheduleSimilar"
             placeholder="<?= htmlattr(__("Be specific and imagine you are asking a colleague")) ?>">
      <div class="form-text">
        {{ title.length }}/300 ·
        <span :class="{'text-danger': title.length > 0 && title.length < minTitle}">
          <?= html(__("at least")) ?> {{ minTitle }} <?= html(__("characters")) ?>
        </span>
      </div>
    </div>

    <div v-if="similar.length" class="alert alert-light border">
      <strong class="small"><?= html(__("Similar questions - maybe yours is already answered:")) ?></strong>
      <ul class="mb-0 small mt-2">
        <li v-for="item in similar" :key="item.id">
          <a :href="askbot.baseUrl + '/question/' + item.id + '/' + item.slug" target="_blank" rel="noopener">{{ item.title }}</a>
          <span class="text-secondary">({{ item.answer_count }} <?= html(__("answers")) ?>)</span>
        </li>
      </ul>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold"><?= html(__("What did you try and what happened?")) ?></label>
      <?php PageEngine::html("box_editor", ["model" => "body", "rows" => 16]); ?>
    </div>

    <div class="mb-3">
      <label class="form-label fw-semibold"><?= html(__("Tags")) ?></label>
      <div class="d-flex flex-wrap gap-1 mb-1">
        <span class="badge text-bg-primary" v-for="tag in tags" :key="tag">
          {{ tag }}
          <button type="button" class="btn-close btn-close-white btn-sm ms-1" @click="removeTag(tag)"></button>
        </span>
      </div>
      <div class="position-relative">
        <input class="form-control" v-model="tagInput" :disabled="tags.length >= maxTags"
               @input="scheduleTagSuggest" @keydown.enter.prevent="addTag(tagInput)"
               @keydown.188.prevent="addTag(tagInput)"
               placeholder="<?= htmlattr(__("e.g. php mysql performance")) ?>">
        <div v-if="tagSuggestions.length" class="list-group position-absolute w-100 shadow" style="z-index: 20">
          <button type="button" class="list-group-item list-group-item-action py-1"
                  v-for="suggestion in tagSuggestions" :key="suggestion.name" @click="addTag(suggestion.name)">
            {{ suggestion.name }} <span class="text-secondary small">&times;{{ suggestion.question_count }}</span>
          </button>
        </div>
      </div>
      <div class="form-text"><?= html(__("Up to")) ?> {{ maxTags }} <?= html(__("tags. Press enter or comma to add one.")) ?></div>
    </div>

    <div v-if="mode === 'edit'" class="mb-3">
      <label class="form-label"><?= html(__("What did you change?")) ?></label>
      <input class="form-control" v-model="comment" maxlength="255" placeholder="<?= htmlattr(__("added the error message")) ?>">
    </div>

    <div class="d-flex gap-2">
      <button class="btn btn-primary" :disabled="!canSubmit || busy" @click="submit">
        <i v-if="busy" class="fa-solid fa-spinner fa-spin me-1"></i>
        {{ mode === 'edit' ? <?= json_encode(__("Save changes")) ?> : <?= json_encode(__("Publish your question")) ?> }}
      </button>
      <a class="btn btn-outline-secondary" href="<?= htmlattr(url("questions")) ?>"><?= html(__("Cancel")) ?></a>
    </div>
  </div>

  <div class="col-lg-4">
    <?php PageEngine::html("box_sidebar", ["show" => ["help", "tags"]]); ?>
  </div>
</div>
</div>

<script>
const { createApp } = Vue;

createApp({
  data() {
    return Object.assign(<?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
      askbot: window.askbot,
      tagInput: "",
      tagSuggestions: [],
      similar: [],
      comment: "",
      preview: "",
      showPreview: false,
      previewTimer: null,
      similarTimer: null,
      tagTimer: null,
      busy: false
    });
  },

  computed: {
    canSubmit() {
      return this.title.trim().length >= this.minTitle
          && this.body.trim().length >= this.minBody
          && this.tags.length >= 1;
    }
  },

  methods: {
    addTag(name) {
      name = (name || "").trim().toLowerCase().replace(/[^a-z0-9+#.-]/g, "");
      if (!name || this.tags.includes(name) || this.tags.length >= this.maxTags) { this.tagInput = ""; return; }
      this.tags.push(name);
      this.tagInput = "";
      this.tagSuggestions = [];
    },

    removeTag(name) {
      this.tags = this.tags.filter(tag => tag !== name);
    },

    scheduleTagSuggest() {
      clearTimeout(this.tagTimer);
      this.tagTimer = setTimeout(async () => {
        if (this.tagInput.trim().length < 1) { this.tagSuggestions = []; return; }
        try {
          const result = await askbot.api("tag.suggest", { q: this.tagInput }, "GET");
          this.tagSuggestions = (result.tags || []).filter(tag => !this.tags.includes(tag.name));
        } catch (e) { this.tagSuggestions = []; }
      }, 200);
    },

    scheduleSimilar() {
      clearTimeout(this.similarTimer);
      this.similarTimer = setTimeout(async () => {
        if (this.title.trim().length < 12) { this.similar = []; return; }
        try {
          const result = await askbot.api("question.similar", { title: this.title, exclude: this.id }, "GET");
          this.similar = result.questions || [];
        } catch (e) { this.similar = []; }
      }, 500);
    },

    schedulePreview() {
      clearTimeout(this.previewTimer);
      this.previewTimer = setTimeout(this.renderPreview, 400);
    },

    async renderPreview() {
      if (!this.showPreview || this.body.trim() === "") { this.preview = ""; return; }
      try { this.preview = (await askbot.api("site.preview", { body: this.body })).html; } catch (e) {}
    },

    insertMarkup(before, after) {
      const editor = this.$refs.editor;
      if (!editor) return;
      const start = editor.selectionStart, end = editor.selectionEnd;
      const selected = this.body.substring(start, end);
      this.body = this.body.substring(0, start) + before + selected + after + this.body.substring(end);
      this.$nextTick(() => {
        editor.focus();
        editor.selectionStart = start + before.length;
        editor.selectionEnd = start + before.length + selected.length;
      });
      this.schedulePreview();
    },

    async uploadImage(event) {
      const file = event.target.files[0];
      if (!file) return;
      const body = new FormData();
      body.append("file", file);
      body.append("csrf_token", askbot.csrfToken);
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
        const payload = { title: this.title, body: this.body, tags: this.tags };
        let result;
        if (this.mode === "edit") {
          payload.id = this.id;
          payload.comment = this.comment;
          result = await askbot.api("question.update", payload);
        } else {
          result = await askbot.api("question.create", payload);
        }
        localStorage.removeItem("askbot-draft");
        location.href = result.url;
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    },

    saveDraft() {
      if (this.mode === "edit") return;
      localStorage.setItem("askbot-draft", JSON.stringify({ title: this.title, body: this.body, tags: this.tags }));
    }
  },

  watch: {
    showPreview(value) { if (value) this.renderPreview(); },
    title() { this.saveDraft(); },
    body() { this.saveDraft(); }
  },

  mounted() {
    if (this.mode === "ask" && this.title === "" && this.body === "") {
      try {
        const draft = JSON.parse(localStorage.getItem("askbot-draft") || "null");
        if (draft && (draft.title || draft.body)) {
          this.title = draft.title || "";
          this.body = draft.body || "";
          this.tags = draft.tags || [];
          askbot.toast(<?= json_encode(__("Restored your unsaved draft.")) ?>, "secondary");
        }
      } catch (e) {}
    }
  }
}).mount("#askApp");
</script>

<?php PageEngine::html("footer"); ?>
