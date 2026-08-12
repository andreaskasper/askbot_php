<?php
/**
 * header.php - opens the document and renders the navigation bar.
 *
 * @param array $params title, description, canonical, feeds, body_class, noindex
 */
i18n::init("core");

$siteTitle = (string)Config::get("site_title", "Askbot");
$pageTitle = trim((string)($params["title"] ?? ""));
$fullTitle = $pageTitle === "" ? $siteTitle . " - " . Config::get("site_tagline") : $pageTitle . " - " . $siteTitle;
$description = (string)($params["description"] ?? Config::get("site_description"));
$unread = MyUser::isLoggedIn() ? Notification::unreadCount(MyUser::id()) : 0;
$flashes = PageEngine::takeFlash();
?><!doctype html>
<html lang="<?= htmlattr(i18n::lang()) ?>" data-bs-theme="<?= htmlattr(Config::get("site_theme") === "dark" ? "dark" : "light") ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= html($fullTitle) ?></title>
<meta name="description" content="<?= htmlattr(mb_substr($description, 0, 300)) ?>">
<?php if (!empty($params["noindex"])) { ?><meta name="robots" content="noindex,follow"><?php } ?>
<?php if (!empty($params["canonical"])) { ?><link rel="canonical" href="<?= htmlattr($params["canonical"]) ?>"><?php } ?>
<meta property="og:site_name" content="<?= htmlattr($siteTitle) ?>">
<meta property="og:title" content="<?= htmlattr($pageTitle !== "" ? $pageTitle : $siteTitle) ?>">
<meta property="og:description" content="<?= htmlattr(mb_substr($description, 0, 300)) ?>">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary">
<link rel="alternate" type="application/rss+xml" title="<?= htmlattr($siteTitle . " - " . __("Newest questions")) ?>" href="<?= htmlattr(url("feeds/questions.rss")) ?>">
<link rel="search" type="application/opensearchdescription+xml" title="<?= htmlattr($siteTitle) ?>" href="<?= htmlattr(url("opensearch.xml")) ?>">
<link rel="manifest" href="<?= htmlattr(url("manifest.webmanifest")) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet">
<link href="<?= htmlattr(asset("css/main.css")) ?>" rel="stylesheet">
<?php if (!empty($params["jsonld"])) { ?>
<script type="application/ld+json"><?= json_encode($params["jsonld"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
<?php } ?>
</head>
<body class="<?= htmlattr($params["body_class"] ?? "") ?>">

<nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= htmlattr(url("/")) ?>">
      <i class="fa-solid fa-circle-question text-primary me-1"></i><?= html($siteTitle) ?>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainnav" aria-label="<?= htmlattr(__("Menu")) ?>">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainnav">
      <ul class="navbar-nav me-auto">
        <li class="nav-item"><a class="nav-link" href="<?= htmlattr(url("questions")) ?>"><?= html(__("Questions")) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="<?= htmlattr(url("tags")) ?>"><?= html(__("Tags")) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="<?= htmlattr(url("users")) ?>"><?= html(__("Users")) ?></a></li>
        <li class="nav-item"><a class="nav-link" href="<?= htmlattr(url("badges")) ?>"><?= html(__("Badges")) ?></a></li>
      </ul>

      <form class="d-flex me-lg-3 my-2 my-lg-0 position-relative" role="search" action="<?= htmlattr(url("search")) ?>" method="get" id="searchBox">
        <input class="form-control form-control-sm" type="search" name="q" style="min-width: 15rem"
               placeholder="<?= htmlattr(__("Search…")) ?>" aria-label="<?= htmlattr(__("Search")) ?>"
               value="<?= htmlattr($_GET["q"] ?? "") ?>" autocomplete="off">
      </form>

      <ul class="navbar-nav align-items-lg-center">
        <?php if (MyUser::isLoggedIn()) { ?>
          <li class="nav-item"><a class="nav-link position-relative" href="<?= htmlattr(url("notifications")) ?>" title="<?= htmlattr(__("Notifications")) ?>">
            <i class="fa-solid fa-bell"></i>
            <?php if ($unread > 0) { ?><span class="badge rounded-pill bg-danger position-absolute top-0 start-100 translate-middle"><?= (int)$unread ?></span><?php } ?>
          </a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" data-bs-toggle="dropdown">
              <img src="<?= htmlattr(MyUser::user()->avatar(24)) ?>" width="24" height="24" class="rounded" alt="">
              <span class="d-none d-lg-inline"><?= html(MyUser::name()) ?></span>
              <span class="badge text-bg-secondary"><?= (int)MyUser::karma() ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= htmlattr(MyUser::user()->permalink()) ?>"><i class="fa-solid fa-user me-2"></i><?= html(__("My profile")) ?></a></li>
              <li><a class="dropdown-item" href="<?= htmlattr(url("account/settings")) ?>"><i class="fa-solid fa-gear me-2"></i><?= html(__("Settings")) ?></a></li>
              <?php if (MyUser::isModerator()) { ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="<?= htmlattr(url("admin/moderation")) ?>"><i class="fa-solid fa-shield-halved me-2"></i><?= html(__("Moderation")) ?>
                  <?php $openFlags = Flag::countOpen(); if ($openFlags > 0) { ?><span class="badge text-bg-danger ms-1"><?= (int)$openFlags ?></span><?php } ?>
                </a></li>
              <?php } ?>
              <?php if (MyUser::isAdmin()) { ?>
                <li><a class="dropdown-item" href="<?= htmlattr(url("admin/settings")) ?>"><i class="fa-solid fa-sliders me-2"></i><?= html(__("Administration")) ?></a></li>
              <?php } ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="<?= htmlattr(url("account/signout")) ?>"><i class="fa-solid fa-right-from-bracket me-2"></i><?= html(__("Sign out")) ?></a></li>
            </ul>
          </li>
        <?php } else { ?>
          <li class="nav-item"><a class="nav-link" href="<?= htmlattr(url("account/signin")) ?>"><?= html(__("Sign in")) ?></a></li>
          <li class="nav-item"><a class="btn btn-sm btn-primary ms-lg-2" href="<?= htmlattr(url("account/signup")) ?>"><?= html(__("Sign up")) ?></a></li>
        <?php } ?>
        <li class="nav-item ms-lg-2">
          <button class="btn btn-sm btn-outline-secondary" type="button" id="themeToggle" title="<?= htmlattr(__("Toggle dark mode")) ?>">
            <i class="fa-solid fa-circle-half-stroke"></i>
          </button>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main class="container py-4">
<?php foreach ($flashes as $flash) { ?>
  <div class="alert alert-<?= htmlattr($flash["type"]) ?> alert-dismissible fade show" role="alert">
    <?= html($flash["text"]) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?= htmlattr(__("Close")) ?>"></button>
  </div>
<?php } ?>
