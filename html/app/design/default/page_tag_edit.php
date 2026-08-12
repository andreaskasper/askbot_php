<?php
/**
 * page_tag_edit.php - the tag wiki.
 */
i18n::init(__FILE__);

$name = Slug::tag((string)$params["name"]);
$tag = Tag::byName($name);
if ($tag === []) PageEngine::error(404, __("Unknown tag."));

$canEdit = MyUser::can("tag_wiki");

PageEngine::html("header", ["title" => sprintf(__("Tag wiki: %s"), $name), "noindex" => true]);
?>
<div id="tagApp" v-cloak class="row g-4">
  <div class="col-lg-8">
    <h1 class="h4"><?= html(sprintf(__("Tag wiki: %s"), $name)) ?></h1>
    <p class="text-secondary"><?= html(sprintf(__("%s questions use this tag."), i18n::shortNumber($tag["question_count"]))) ?></p>

    <?php if ($canEdit) { ?>
      <?php PageEngine::html("box_editor", ["model" => "body", "rows" => 10, "placeholder" => __("Explain when this tag should be used.")]); ?>
      <button class="btn btn-primary mt-3" :disabled="busy" @click="save">
        <i v-if="busy" class="fa-solid fa-spinner fa-spin me-1"></i><?= html(__("Save tag wiki")) ?>
      </button>
    <?php } else { ?>
      <div class="post-body border rounded p-3" v-pre><?= $tag["description_html"] ?: "<p class='text-secondary'>" . html(__("No description yet.")) . "</p>" ?></div>
      <p class="karma-hint mt-2">
        <?= html(sprintf(__("You need %d karma to edit tag wikis."), Config::int("threshold_tag_wiki", 1500))) ?>
      </p>
    <?php } ?>

    <h2 class="h6 mt-4"><?= html(__("Synonyms")) ?></h2>
    <?php $synonyms = Tag::synonyms($name); ?>
    <?php if ($synonyms === []) { ?>
      <p class="text-secondary small"><?= html(__("No synonyms point to this tag.")) ?></p>
    <?php } else { ?>
      <ul class="small">
        <?php foreach ($synonyms as $synonym) { ?>
          <li><code><?= html($synonym["source_name"]) ?></code> &rarr; <code><?= html($name) ?></code>
            <span class="text-secondary">(<?= (int)$synonym["usage_count"] ?>&times;)</span></li>
        <?php } ?>
      </ul>
    <?php } ?>

    <?php if ($canEdit) { ?>
      <div class="input-group input-group-sm" style="max-width: 26rem">
        <input class="form-control" v-model="synonym" placeholder="<?= htmlattr(__("old tag name")) ?>">
        <button class="btn btn-outline-primary" :disabled="!synonym || busy" @click="addSynonym"><?= html(__("Add synonym")) ?></button>
      </div>
    <?php } ?>
  </div>

  <div class="col-lg-4">
    <?php PageEngine::html("box_sidebar", ["show" => ["tags"]]); ?>
  </div>
</div>

<script>
const { createApp } = Vue;
createApp({
  data() {
    return {
      name: <?= json_encode($name) ?>,
      body: <?= json_encode((string)$tag["description_md"]) ?>,
      synonym: "",
      preview: "",
      showPreview: false,
      previewTimer: null,
      busy: false
    };
  },
  methods: {
    schedulePreview() { clearTimeout(this.previewTimer); this.previewTimer = setTimeout(this.renderPreview, 400); },
    async renderPreview() {
      if (!this.showPreview) return;
      try { this.preview = (await askbot.api("site.preview", { body: this.body })).html; } catch (e) {}
    },
    insertMarkup(before, after) {
      const editor = this.$refs.editor;
      if (!editor) return;
      const start = editor.selectionStart, end = editor.selectionEnd;
      const selected = this.body.substring(start, end);
      this.body = this.body.substring(0, start) + before + selected + after + this.body.substring(end);
      this.$nextTick(() => editor.focus());
    },
    uploadImage() {},
    async save() {
      this.busy = true;
      try {
        await askbot.api("tag.savewiki", { name: this.name, description: this.body });
        askbot.toast(askbot.i18n.saved);
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    },
    async addSynonym() {
      this.busy = true;
      try {
        await askbot.api("tag.addsynonym", { source: this.synonym, target: this.name });
        location.reload();
      } catch (e) { askbot.toast(e.message, "danger"); }
      finally { this.busy = false; }
    }
  },
  watch: { showPreview(v) { if (v) this.renderPreview(); } }
}).mount("#tagApp");
</script>
<?php PageEngine::html("footer"); ?>
