<?php
/**
 * admin/page_health.php - "is anything obviously wrong?"
 */
i18n::init(__FILE__);

$db = new SQL(0);
$checks = [];

$checks[] = ["PHP " . PHP_VERSION, version_compare(PHP_VERSION, "8.2", ">="), __("PHP 8.2 or newer is required")];
foreach (["pdo_mysql", "mbstring", "json", "gd"] as $extension) {
    $checks[] = ["ext/" . $extension, extension_loaded($extension), sprintf(__("the %s extension is missing"), $extension)];
}
$checks[] = [__("database"), true, ""];
$checks[] = [__("uploads directory writable"), is_writable($_ENV["webroot"] . "/uploads"), $_ENV["webroot"] . "/uploads"];
$checks[] = [__("APP_SECRET set"), (string)Config::env("APP_SECRET", "") !== "" && Config::env("APP_SECRET") !== "change-me", __("set a long random APP_SECRET")];
$checks[] = [__("mail queue drained"), $db->cmdint('SELECT COUNT(*) FROM mail_queue WHERE status = "pending" AND created_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)') === 0, __("run the mailer bot")];
$checks[] = [__("HTTPS"), (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") || ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https", __("serve the site over TLS")];
$checks[] = [__("full text index"), $db->cmdrow('SHOW INDEX FROM questions WHERE Key_name = "ft_questions"') !== [], __("the fulltext index on questions is missing")];

PageEngine::html("header", ["title" => __("Health"), "noindex" => true]);
PageEngine::html("admin/box_nav", ["active" => "health"]);
?>
<h1 class="h4 mb-3"><?= html(__("Health")) ?></h1>
<table class="table table-sm">
  <tbody>
  <?php foreach ($checks as [$label, $ok, $hint]) { ?>
    <tr>
      <td style="width: 3rem"><i class="fa-solid <?= $ok ? "fa-circle-check text-success" : "fa-triangle-exclamation text-warning" ?>"></i></td>
      <td><?= html($label) ?></td>
      <td class="small text-secondary"><?= $ok ? "" : html($hint) ?></td>
    </tr>
  <?php } ?>
  </tbody>
</table>

<h2 class="h6 mt-4"><?= html(__("Background jobs")) ?></h2>
<pre class="small bg-body-tertiary p-3 rounded">php html/app/app.php cron --loop --sleep=60
php html/app/app.php bot -t mailer
php html/app/app.php bot -t badges
php html/app/app.php bot -t maintenance</pre>
<?php PageEngine::html("footer"); ?>
