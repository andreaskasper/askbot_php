<?php
/**
 * box_pagination.php
 *
 * @param array $params page, pages, base (path without page parameter)
 */
$page = max(1, (int)($params["page"] ?? 1));
$pages = max(1, (int)($params["pages"] ?? 1));
if ($pages < 2) return;

$query = $_GET;
unset($query["page"]);
$base = ($params["base"] ?? Url::current()) . (count($query) > 0 ? "?" . http_build_query($query) . "&" : "?");

$link = function (int $target) use ($base) { return htmlattr($base . "page=" . $target); };
$window = 2;
?>
<nav class="mt-4" aria-label="<?= htmlattr(__("Pagination")) ?>">
  <ul class="pagination pagination-sm">
    <li class="page-item <?= $page <= 1 ? "disabled" : "" ?>">
      <a class="page-link" href="<?= $link(max(1, $page - 1)) ?>" rel="prev"><?= html(__("Previous")) ?></a>
    </li>
    <?php
    for ($i = 1; $i <= $pages; $i++) {
        $isEdge = ($i <= 1 || $i > $pages - 1);
        $isNear = abs($i - $page) <= $window;
        if (!$isEdge && !$isNear) {
            if ($i === 2 || $i === $pages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
            continue;
        }
    ?>
      <li class="page-item <?= $i === $page ? "active" : "" ?>"><a class="page-link" href="<?= $link($i) ?>"><?= $i ?></a></li>
    <?php } ?>
    <li class="page-item <?= $page >= $pages ? "disabled" : "" ?>">
      <a class="page-link" href="<?= $link(min($pages, $page + 1)) ?>" rel="next"><?= html(__("Next")) ?></a>
    </li>
  </ul>
</nav>
