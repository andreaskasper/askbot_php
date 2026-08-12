<?php
/**
 * page_user.php - a member profile with its tabs.
 *
 * @param array $params id, tab (activity|questions|answers|karma|badges|inbox|edit)
 */
i18n::init(__FILE__);

$user = new User((int)$params["id"]);
if (!$user->exists() || $user->deleted_at !== null) PageEngine::error(404, __("This profile does not exist."));

$tab = (string)($params["tab"] ?? "activity");
$isSelf = MyUser::id() === $user->id();
$db = new SQL(0);

if (!$isSelf && !Firewall::isCrawler()) {
    $db->cmd('UPDATE users SET profile_views = profile_views + 1 WHERE id = "{0}"', [$user->id()]);
}

$tabs = [
    "activity"  => __("Activity"),
    "questions" => __("Questions"),
    "answers"   => __("Answers"),
    "karma"     => __("Karma"),
    "badges"    => __("Badges"),
];
if ($isSelf || MyUser::isModerator()) {
    $tabs["inbox"] = __("Inbox");
    $tabs["edit"]  = __("Edit profile");
}

PageEngine::html("header", [
    "title"       => $user->displayName(),
    "description" => Markdown::toText((string)$user->bio_md, 160) ?: sprintf(__("Profile of %s"), $user->displayName()),
    "canonical"   => $user->permalink(),
]);
?>
<div class="row g-4">
  <div class="col-lg-3">
    <div class="text-center mb-3">
      <img src="<?= htmlattr($user->avatar(128)) ?>" width="128" height="128" class="rounded shadow-sm" alt="">
      <h1 class="h5 mt-3 mb-1"><?= html($user->displayName()) ?></h1>
      <?php if ($user->isStaff()) { ?><span class="badge text-bg-danger"><?= html((string)$user->role) ?></span><?php } ?>
      <?php if ($user->isSuspended()) { ?><span class="badge text-bg-warning"><?= html(__("suspended")) ?></span><?php } ?>
      <div class="fs-4 fw-semibold mt-2"><?= html(number_format((int)$user->karma)) ?></div>
      <div class="text-secondary small"><?= html(__("karma")) ?></div>
      <?php $badges = $user->badgeCounts(); ?>
      <div class="mt-2 small">
        <span class="badge-dot badge-gold"><?= (int)$badges["gold"] ?></span>
        <span class="badge-dot badge-silver ms-2"><?= (int)$badges["silver"] ?></span>
        <span class="badge-dot badge-bronze ms-2"><?= (int)$badges["bronze"] ?></span>
      </div>
    </div>

    <ul class="list-unstyled small text-secondary">
      <?php if ((string)$user->real_name !== "") { ?><li><i class="fa-solid fa-id-card me-2"></i><?= html((string)$user->real_name) ?></li><?php } ?>
      <?php if ((string)$user->location !== "") { ?><li><i class="fa-solid fa-location-dot me-2"></i><?= html((string)$user->location) ?></li><?php } ?>
      <?php if ((string)$user->website !== "") { ?>
        <li><i class="fa-solid fa-globe me-2"></i><a rel="nofollow noopener" href="<?= htmlattr((string)$user->website) ?>"><?= html(parse_url((string)$user->website, PHP_URL_HOST) ?: (string)$user->website) ?></a></li>
      <?php } ?>
      <li><i class="fa-solid fa-cake-candles me-2"></i><?= html(sprintf(__("member since %s"), i18n::date((string)$user->created_at, "M Y"))) ?></li>
      <li><i class="fa-solid fa-clock me-2"></i><?= html(sprintf(__("last seen %s"), i18n::ago((string)$user->last_seen_at))) ?></li>
      <li><i class="fa-solid fa-eye me-2"></i><?= html(sprintf(__("%d profile views"), (int)$user->profile_views)) ?></li>
    </ul>

    <?php if (MyUser::isLoggedIn() && !$isSelf) { ?>
      <a class="btn btn-outline-secondary btn-sm w-100" href="<?= htmlattr(url("users/" . $user->id() . "/" . $user->slug . "/inbox")) ?>?to=<?= $user->id() ?>">
        <i class="fa-regular fa-envelope me-1"></i><?= html(__("Send a message")) ?>
      </a>
    <?php } ?>
    <?php if (MyUser::isModerator() && !$isSelf) { ?>
      <a class="btn btn-outline-danger btn-sm w-100 mt-2" href="<?= htmlattr(url("admin/users")) ?>?q=<?= rawurlencode($user->displayName()) ?>">
        <i class="fa-solid fa-shield-halved me-1"></i><?= html(__("Moderate")) ?>
      </a>
    <?php } ?>
  </div>

  <div class="col-lg-9">
    <ul class="nav nav-tabs mb-3">
      <?php foreach ($tabs as $key => $label) { ?>
        <li class="nav-item">
          <a class="nav-link <?= $tab === $key ? "active" : "" ?>"
             href="<?= htmlattr(url("users/" . $user->id() . "/" . $user->slug . ($key === "activity" ? "" : "/" . $key))) ?>"><?= html($label) ?></a>
        </li>
      <?php } ?>
    </ul>

    <?php if ($tab === "activity") { ?>
      <?php if ((string)$user->bio_html !== "") { ?>
        <div class="post-body border rounded p-3 mb-4" v-pre><?= $user->bio_html ?></div>
      <?php } ?>
      <div class="row g-3">
        <div class="col-md-6">
          <h2 class="h6"><?= html(__("Newest questions")) ?></h2>
          <?php $rows = $db->cmdrows('SELECT id, title, slug, score, answer_count, created_at FROM questions WHERE author_id = "{0}" AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 10', [$user->id()]); ?>
          <ul class="list-group list-group-flush small">
            <?php foreach ($rows as $row) { ?>
              <li class="list-group-item px-0">
                <span class="badge text-bg-light float-end ms-2"><?= (int)$row["score"] ?></span>
                <a href="<?= htmlattr(url("question/" . (int)$row["id"] . "/" . $row["slug"])) ?>"><?= html($row["title"]) ?></a>
              </li>
            <?php } ?>
            <?php if ($rows === []) { ?><li class="list-group-item px-0 text-secondary"><?= html(__("nothing yet")) ?></li><?php } ?>
          </ul>
        </div>
        <div class="col-md-6">
          <h2 class="h6"><?= html(__("Newest answers")) ?></h2>
          <?php $rows = $db->cmdrows('SELECT a.id, a.score, a.is_accepted, q.id AS qid, q.title, q.slug FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.author_id = "{0}" AND a.deleted_at IS NULL ORDER BY a.created_at DESC LIMIT 10', [$user->id()]); ?>
          <ul class="list-group list-group-flush small">
            <?php foreach ($rows as $row) { ?>
              <li class="list-group-item px-0">
                <span class="badge <?= (int)$row["is_accepted"] === 1 ? "text-bg-success" : "text-bg-light" ?> float-end ms-2"><?= (int)$row["score"] ?></span>
                <a href="<?= htmlattr(url("question/" . (int)$row["qid"] . "/" . $row["slug"] . "#answer-" . (int)$row["id"])) ?>"><?= html($row["title"]) ?></a>
              </li>
            <?php } ?>
            <?php if ($rows === []) { ?><li class="list-group-item px-0 text-secondary"><?= html(__("nothing yet")) ?></li><?php } ?>
          </ul>
        </div>
      </div>

    <?php } elseif ($tab === "questions") { ?>
      <?php
      $result = Question::search(["user" => $user->id(), "sort" => "newest", "page" => (int)($_GET["page"] ?? 1)]);
      foreach ($result["items"] as $row) PageEngine::html("box_question_summary", ["row" => $row, "author" => $user]);
      if ($result["items"] === []) echo '<p class="text-secondary">' . html(__("No questions yet.")) . '</p>';
      PageEngine::html("box_pagination", ["page" => $result["page"], "pages" => $result["pages"]]);
      ?>

    <?php } elseif ($tab === "answers") { ?>
      <?php $rows = $db->cmdrows('SELECT a.*, q.id AS qid, q.title, q.slug FROM answers a JOIN questions q ON q.id = a.question_id WHERE a.author_id = "{0}" AND a.deleted_at IS NULL ORDER BY a.created_at DESC LIMIT 50', [$user->id()]); ?>
      <div class="list-group list-group-flush">
        <?php foreach ($rows as $row) { ?>
          <div class="list-group-item px-0">
            <span class="badge <?= (int)$row["is_accepted"] === 1 ? "text-bg-success" : "text-bg-light" ?> float-end"><?= (int)$row["score"] ?></span>
            <a href="<?= htmlattr(url("question/" . (int)$row["qid"] . "/" . $row["slug"] . "#answer-" . (int)$row["id"])) ?>"><?= html($row["title"]) ?></a>
            <div class="small text-secondary"><?= html(Markdown::toText((string)$row["body_md"], 160)) ?></div>
          </div>
        <?php } ?>
      </div>

    <?php } elseif ($tab === "karma") { ?>
      <table class="table table-sm">
        <thead><tr><th><?= html(__("When")) ?></th><th><?= html(__("Reason")) ?></th><th class="text-end"><?= html(__("Points")) ?></th></tr></thead>
        <tbody>
        <?php foreach (Karma::history($user->id(), 100) as $entry) { ?>
          <tr>
            <td class="text-secondary small"><?= html(i18n::date((string)$entry["created_at"], "Y-m-d H:i")) ?></td>
            <td>
              <?= html(Karma::reasonLabel((string)$entry["reason"])) ?>
              <?php if ($entry["post_id"] !== null && $entry["post_type"] !== "none") { ?>
                <a class="small" href="<?= htmlattr(Post::permalink((string)$entry["post_type"], (int)$entry["post_id"])) ?>"><?= html(__("post")) ?></a>
              <?php } ?>
            </td>
            <td class="text-end fw-semibold <?= (int)$entry["points"] >= 0 ? "text-success" : "text-danger" ?>">
              <?= (int)$entry["points"] > 0 ? "+" : "" ?><?= (int)$entry["points"] ?>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>

    <?php } elseif ($tab === "badges") { ?>
      <div class="row row-cols-1 row-cols-md-2 g-3">
        <?php foreach (Badge::forUser($user->id()) as $badge) { ?>
          <div class="col"><div class="card h-100"><div class="card-body py-2">
            <a class="fw-semibold text-decoration-none <?= htmlattr(Badge::levelClass((string)$badge["level"])) ?>" href="<?= htmlattr(url("badges/" . (int)$badge["id"] . "/" . Slug::make((string)$badge["name"]))) ?>">
              <span class="badge-dot"></span><?= html($badge["name"]) ?>
            </a>
            <div class="small text-secondary"><?= html($badge["description"]) ?></div>
            <div class="small text-secondary"><?= html(i18n::ago((string)$badge["awarded_at"])) ?></div>
          </div></div></div>
        <?php } ?>
      </div>

    <?php } elseif ($tab === "inbox") { ?>
      <?php PageEngine::html("box_inbox", ["user" => $user]); ?>

    <?php } elseif ($tab === "edit") { ?>
      <?php PageEngine::html("box_profile_form", ["user" => $user]); ?>
    <?php } ?>
  </div>
</div>
<?php PageEngine::html("footer"); ?>
