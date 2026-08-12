<?php
/**
 * box_comments.php - comment list plus the "add a comment" form.
 * Rendered inside the Vue app of the question page.
 *
 * @param array $params post_type, id_expression (JS expression for the post id),
 *                      comments_expression
 */
$postType = $params["post_type"];
$idExpr = $params["id_expression"];
$commentsExpr = $params["comments_expression"];
?>
<div class="comments mt-3">
  <div class="comment" v-for="comment in <?= htmlattr($commentsExpr) ?>" :key="comment.id" :id="'comment-' + comment.id">
    <span v-html="comment.body_html"></span>
    <span class="text-secondary">&ndash;
      <a :href="comment.author.url" class="link-secondary">{{ comment.author.username }}</a>
      <span :title="comment.created_at">{{ ago(comment.created_at) }}</span>
    </span>
    <button v-if="canDeleteComment(comment)" class="btn btn-link btn-sm p-0 text-danger" @click="deleteComment(comment, <?= htmlattr($commentsExpr) ?>)" title="<?= htmlattr(__("Delete")) ?>">
      <i class="fa-solid fa-xmark"></i>
    </button>
  </div>

  <?php if (MyUser::isLoggedIn()) { ?>
    <div class="mt-2">
      <button v-if="commentFormFor !== '<?= htmlattr($postType) ?>:' + <?= htmlattr($idExpr) ?>"
              class="btn btn-link btn-sm p-0 text-secondary"
              @click="openCommentForm('<?= htmlattr($postType) ?>', <?= htmlattr($idExpr) ?>)">
        <?= html(__("add a comment")) ?>
      </button>
      <div v-else class="input-group input-group-sm">
        <input class="form-control" v-model="commentDraft" maxlength="1000"
               placeholder="<?= htmlattr(__("Use comments to ask for clarification.")) ?>"
               @keyup.enter="submitComment('<?= htmlattr($postType) ?>', <?= htmlattr($idExpr) ?>, <?= htmlattr($commentsExpr) ?>)">
        <button class="btn btn-primary" :disabled="commentDraft.trim().length < 2 || busy"
                @click="submitComment('<?= htmlattr($postType) ?>', <?= htmlattr($idExpr) ?>, <?= htmlattr($commentsExpr) ?>)">
          <?= html(__("Add")) ?>
        </button>
        <button class="btn btn-outline-secondary" @click="commentFormFor = null"><?= html(__("Cancel")) ?></button>
      </div>
    </div>
  <?php } ?>
</div>
