<?php
/**
 * page_revisions.php - edit history with a diff between neighbours.
 *
 * @param array $params post_type, id
 */
i18n::init(__FILE__);

$postType = (string)$params["post_type"];
$postId = (int)$params["id"];
$revisions = Revision::all($postType, $postId);
if ($revisions === []) PageEngine::error(404);

$questionId = Post::questionId($postType, $postId);

PageEngine::html("header", ["title" => __("Revision history"), "noindex" => true]);
?>
<h1 class="h4"><?= html(__("Revision history")) ?></h1>
<p><a href="<?= htmlattr(Post::permalink($postType, $postId)) ?>"><?= html(__("back to the post")) ?></a></p>

<?php foreach ($revisions as $index => $revision) {
    $previous = $revisions[$index + 1] ?? null;
?>
  <div class="card mb-3">
    <div class="card-header py-2 small d-flex justify-content-between">
      <span>
        <strong><?= html(__("revision")) ?> <?= (int)$revision["revision"] ?></strong>
        <?php if (($revision["comment"] ?? "") !== "") { ?>&ndash; <?= html($revision["comment"]) ?><?php } ?>
      </span>
      <span class="text-secondary">
        <?php if ($revision["username"] !== null) { ?>
          <a class="link-secondary" href="<?= htmlattr(url("users/" . (int)$revision["user_id"] . "/" . $revision["slug"])) ?>"><?= html($revision["username"]) ?></a>
        <?php } ?>
        <?= html(i18n::ago((string)$revision["created_at"])) ?>
      </span>
    </div>
    <div class="card-body py-2">
      <?php if (($revision["title"] ?? "") !== "") { ?><div class="fw-semibold mb-2"><?= html($revision["title"]) ?></div><?php } ?>
      <?php if ($previous !== null) { ?>
        <?= Revision::diffHtml((string)$previous["body_md"], (string)$revision["body_md"]) ?>
      <?php } else { ?>
        <pre class="small mb-0" style="white-space: pre-wrap"><?= html($revision["body_md"]) ?></pre>
      <?php } ?>
    </div>
  </div>
<?php } ?>
<?php PageEngine::html("footer"); ?>
