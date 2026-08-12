<?php
/**
 * mail/layout.php - wrapper around every outgoing message.
 * @param array $params subject, body
 */
?>
<!doctype html>
<html><head><meta charset="utf-8"><title><?= html($params["subject"] ?? "") ?></title></head>
<body style="margin:0;padding:24px;background:#f6f7f9;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;color:#212529">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:8px">
    <tr><td style="padding:24px 28px 8px">
      <a href="<?= htmlattr(Config::baseUrl()) ?>" style="font-size:18px;font-weight:600;color:#0d6efd;text-decoration:none">
        <?= html(Config::get("site_title")) ?>
      </a>
    </td></tr>
    <tr><td style="padding:8px 28px 24px;font-size:15px;line-height:1.6">
      <?= $params["body"] ?? "" ?>
    </td></tr>
    <tr><td style="padding:16px 28px;border-top:1px solid #eee;font-size:12px;color:#6c757d">
      <?= html(__("You receive this message because of your account on")) ?>
      <a href="<?= htmlattr(Config::baseUrl()) ?>" style="color:#6c757d"><?= html(Config::get("site_title")) ?></a>.
      <br>
      <a href="<?= htmlattr(url("account/settings")) ?>" style="color:#6c757d"><?= html(__("Change your email preferences")) ?></a>
    </td></tr>
  </table>
</body></html>
