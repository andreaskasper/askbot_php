<?php
/**
 * mail/reset_password.php
 * @param array $params user, link
 */
?>
<h1 style="font-size:20px;margin:0 0 12px"><?= html(__("Reset your password")) ?></h1>
<p><?= html(sprintf(__("Someone asked to reset the password of %s."), $params["user"]->displayName())) ?></p>
<p style="margin:24px 0">
  <a href="<?= htmlattr($params["link"]) ?>" style="background:#0d6efd;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none">
    <?= html(__("Choose a new password")) ?>
  </a>
</p>
<p style="font-size:13px;color:#6c757d"><?= html(__("The link is valid for one hour. If this was not you, nothing happened - you can ignore this message.")) ?></p>
