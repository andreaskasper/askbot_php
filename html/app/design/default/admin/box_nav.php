<?php
/**
 * admin/box_nav.php
 * @param array $params active
 */
$items = [
    "settings"   => [__("Settings"), "fa-sliders", url("admin/settings")],
    "moderation" => [__("Moderation"), "fa-shield-halved", url("admin/moderation")],
    "users"      => [__("Users"), "fa-users", url("admin/users")],
    "statistics" => [__("Statistics"), "fa-chart-line", url("admin/statistics")],
    "health"     => [__("Health"), "fa-heart-pulse", url("admin/health")],
];
?>
<ul class="nav nav-pills mb-4">
  <?php foreach ($items as $key => [$label, $icon, $link]) { ?>
    <li class="nav-item">
      <a class="nav-link <?= ($params["active"] ?? "") === $key ? "active" : "" ?>" href="<?= htmlattr($link) ?>">
        <i class="fa-solid <?= htmlattr($icon) ?> me-1"></i><?= html($label) ?>
      </a>
    </li>
  <?php } ?>
</ul>
