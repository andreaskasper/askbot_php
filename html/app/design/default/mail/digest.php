<?php
/**
 * mail/digest.php
 * @param array $params user, items
 */
?>
<h1 style="font-size:20px;margin:0 0 12px"><?= html(sprintf(__("%d things happened while you were away"), count($params["items"]))) ?></h1>
<ul style="padding-left:18px">
<?php foreach ($params["items"] as $item) { ?>
  <li style="margin-bottom:8px">
    <a href="<?= htmlattr($item["url"] ?: Config::baseUrl()) ?>" style="color:#0d6efd"><?= html($item["title"]) ?></a>
    <div style="font-size:12px;color:#6c757d"><?= html(i18n::date((string)$item["created_at"], "j M Y H:i")) ?></div>
  </li>
<?php } ?>
</ul>
<p style="margin-top:24px">
  <a href="<?= htmlattr(url("notifications")) ?>" style="background:#0d6efd;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none">
    <?= html(__("Open your notifications")) ?>
  </a>
</p>
