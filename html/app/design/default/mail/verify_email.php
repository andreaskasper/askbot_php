<?php
/**
 * mail/verify_email.php
 * @param array $params user, link
 */
?>
<h1 style="font-size:20px;margin:0 0 12px"><?= html(sprintf(__("Welcome, %s"), $params["user"]->displayName())) ?></h1>
<p><?= html(__("One click and your account is ready:")) ?></p>
<p style="margin:24px 0">
  <a href="<?= htmlattr($params["link"]) ?>" style="background:#0d6efd;color:#fff;padding:12px 20px;border-radius:6px;text-decoration:none">
    <?= html(__("Confirm my email address")) ?>
  </a>
</p>
<p style="font-size:13px;color:#6c757d">
  <?= html(__("If the button does not work, copy this address into your browser:")) ?><br>
  <span style="word-break:break-all"><?= html($params["link"]) ?></span>
</p>
<p style="font-size:13px;color:#6c757d"><?= html(__("The link is valid for three days. If you did not sign up, just ignore this message.")) ?></p>
