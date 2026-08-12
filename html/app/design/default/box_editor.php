<?php
/**
 * box_editor.php - markdown editor used by the ask, answer and edit forms.
 *
 * Renders the markup only; the surrounding Vue app supplies `body`,
 * `preview`, `renderPreview()` and `insertMarkup()`.
 *
 * @param array $params model (v-model expression), rows, placeholder
 */
$model = $params["model"] ?? "body";
?>
<div class="md-editor">
  <div class="md-toolbar btn-group btn-group-sm mb-1" role="group">
    <button type="button" class="btn btn-outline-secondary" @click="insertMarkup('**', '**')" title="<?= htmlattr(__("Bold")) ?>"><i class="fa-solid fa-bold"></i></button>
    <button type="button" class="btn btn-outline-secondary" @click="insertMarkup('*', '*')" title="<?= htmlattr(__("Italic")) ?>"><i class="fa-solid fa-italic"></i></button>
    <button type="button" class="btn btn-outline-secondary" @click="insertMarkup('`', '`')" title="<?= htmlattr(__("Inline code")) ?>"><i class="fa-solid fa-code"></i></button>
    <button type="button" class="btn btn-outline-secondary" @click="insertMarkup('\n```\n', '\n```\n')" title="<?= htmlattr(__("Code block")) ?>"><i class="fa-solid fa-file-code"></i></button>
    <button type="button" class="btn btn-outline-secondary" @click="insertMarkup('[', '](https://)')" title="<?= htmlattr(__("Link")) ?>"><i class="fa-solid fa-link"></i></button>
    <button type="button" class="btn btn-outline-secondary" @click="insertMarkup('\n> ', '')" title="<?= htmlattr(__("Quote")) ?>"><i class="fa-solid fa-quote-left"></i></button>
    <button type="button" class="btn btn-outline-secondary" @click="insertMarkup('\n- ', '')" title="<?= htmlattr(__("List")) ?>"><i class="fa-solid fa-list-ul"></i></button>
    <button type="button" class="btn btn-outline-secondary" @click="$refs.upload.click()" title="<?= htmlattr(__("Upload an image")) ?>"><i class="fa-solid fa-image"></i></button>
    <input type="file" ref="upload" class="d-none" accept="image/*" @change="uploadImage($event)">
  </div>

  <textarea class="form-control" ref="editor" v-model="<?= htmlattr($model) ?>" @input="schedulePreview"
            rows="<?= (int)($params["rows"] ?? 12) ?>"
            placeholder="<?= htmlattr($params["placeholder"] ?? __("Markdown is supported. Indent code by four spaces or wrap it in ``` fences.")) ?>"></textarea>

  <div class="d-flex justify-content-between align-items-center mt-1 small text-secondary">
    <span>{{ <?= htmlattr($model) ?>.length }} <?= html(__("characters")) ?></span>
    <button type="button" class="btn btn-link btn-sm p-0" @click="showPreview = !showPreview">
      {{ showPreview ? <?= json_encode(__("hide preview")) ?> : <?= json_encode(__("show preview")) ?> }}
    </button>
  </div>

  <div v-show="showPreview" class="md-preview post-body mt-2" v-html="preview"></div>
</div>
