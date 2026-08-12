<?php
/**
 * page_notifications.php
 */
i18n::init(__FILE__);

$rows = Notification::forUser(MyUser::id(), 100);
Notification::markRead(MyUser::id());

PageEngine::html("header", ["title" => __("Notifications"), "noindex" => true]);
?>
<h1 class="h4 mb-3"><?= html(__("Notifications")) ?></h1>

<?php if ($rows === []) { ?>
  <div class="empty-state">
    <i class="fa-regular fa-bell fa-2x mb-3 d-block"></i>
    <?= html(__("Nothing new. Follow a question or a tag to get updates here.")) ?>
  </div>
<?php } else { ?>
  <div class="list-group">
    <?php foreach ($rows as $row) { ?>
      <a class="list-group-item list-group-item-action d-flex gap-3 <?= $row["read_at"] === null ? "list-group-item-light" : "" ?>"
         href="<?= htmlattr($row["url"] ?: url("/")) ?>">
        <i class="fa-solid <?= htmlattr(Notification::icon((string)$row["type"])) ?> mt-1 text-secondary"></i>
        <span class="flex-grow-1"><?= html($row["title"]) ?></span>
        <span class="small text-secondary text-nowrap"><?= html(i18n::ago((string)$row["created_at"])) ?></span>
      </a>
    <?php } ?>
  </div>
<?php } ?>
<?php PageEngine::html("footer"); ?>
