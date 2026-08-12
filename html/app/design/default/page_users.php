<?php
/**
 * page_users.php - the member directory.
 */
i18n::init(__FILE__);

$db = new SQL(0);
$sort = (string)($_GET["sort"] ?? "karma");
$query = trim((string)($_GET["q"] ?? ""));
$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 36;

$where = "deleted_at IS NULL";
$values = [];
if ($query !== "") { $where .= ' AND username LIKE "%{0}%"'; $values[] = $query; }
$order = match ($sort) {
    "newest" => "created_at DESC",
    "name"   => "username ASC",
    "active" => "last_seen_at DESC",
    default  => "karma DESC",
};
$total = $db->cmdint('SELECT COUNT(*) FROM users WHERE ' . $where, $values);
$rows = $db->cmdrows(
    'SELECT * FROM users WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . SQL::int(($page - 1) * $perPage) . ',' . SQL::int($perPage),
    $values
);

PageEngine::html("header", ["title" => __("Users"), "description" => __("The people behind the answers")]);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <h1 class="h4 mb-0"><?= html(sprintf(__("%s users"), number_format($total))) ?></h1>
  <form class="d-flex gap-2" method="get">
    <input class="form-control form-control-sm" name="q" value="<?= htmlattr($query) ?>" placeholder="<?= htmlattr(__("Find a user")) ?>">
    <div class="btn-group btn-group-sm">
      <?php foreach (["karma" => __("Karma"), "newest" => __("New"), "active" => __("Active"), "name" => __("Name")] as $key => $label) { ?>
        <a class="btn btn-outline-secondary <?= $sort === $key ? "active" : "" ?>" href="<?= htmlattr(Url::withParams(["sort" => $key, "page" => null])) ?>"><?= html($label) ?></a>
      <?php } ?>
    </div>
  </form>
</div>

<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3">
  <?php foreach ($rows as $row) { $user = new User((int)$row["id"], $row); ?>
    <div class="col">
      <div class="card h-100"><div class="card-body py-2 d-flex gap-2">
        <img src="<?= htmlattr($user->avatar(48)) ?>" width="48" height="48" class="rounded" alt="">
        <div class="min-w-0">
          <a class="d-block text-truncate fw-semibold text-decoration-none" href="<?= htmlattr($user->permalink()) ?>"><?= html($user->displayName()) ?></a>
          <div class="small text-secondary">
            <span class="badge text-bg-light"><?= html(i18n::shortNumber($row["karma"])) ?></span>
            <?php $badges = $user->badgeCounts(); ?>
            <?php if ($badges["gold"]) { ?><span class="badge-dot badge-gold"><?= (int)$badges["gold"] ?></span><?php } ?>
            <?php if ($badges["silver"]) { ?><span class="badge-dot badge-silver"><?= (int)$badges["silver"] ?></span><?php } ?>
            <?php if ($badges["bronze"]) { ?><span class="badge-dot badge-bronze"><?= (int)$badges["bronze"] ?></span><?php } ?>
          </div>
          <div class="small text-secondary text-truncate"><?= html((string)$row["location"]) ?></div>
        </div>
      </div></div>
    </div>
  <?php } ?>
</div>
<?php PageEngine::html("box_pagination", ["page" => $page, "pages" => (int)ceil($total / $perPage)]); ?>
<?php PageEngine::html("footer"); ?>
