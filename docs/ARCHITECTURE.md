# Architecture

The shape of the code, and why it looks the way it does.

## Principles

1. **Readable beats clever.** Anyone who knows PHP should be able to follow a
   request from `html/index.php` to the SQL statement without a debugger.
2. **Server rendered first.** A question page is complete HTML before any
   JavaScript runs. Vue adds interactivity on top; it never owns the page.
3. **No runtime dependencies.** Markdown, mail, TOTP and everything else are
   part of the repository. `composer install` is only needed for the tests.
4. **Everything the UI can do, the API can do.** The templates and the JSON
   API call the same domain classes.

## Request flow

```
html/index.php
  ├─ autoloader          Question -> app/code/classes/Question.php
  │                      API\question -> app/code/classes/API/question.php
  ├─ Config              environment variables > config table > defaults
  ├─ SQL::init(0, …)     one PDO connection, opened lazily
  ├─ Session::start()    strict, http only, SameSite=Lax cookie
  ├─ MyUser::load()      session or "remember me" cookie
  ├─ i18n::detect()      ?_lang > session > Accept-Language > site default
  └─ web\Routing::start()
       ├─ Firewall::guard()      security headers + global rate limit
       ├─ /api/<ns>.<method>.<format>  -> API::run()
       ├─ exact routes                 -> PageEngine::html("page_x")
       └─ pattern routes               -> PageEngine::html("page_x", [...])
```

Every branch of the router ends in a template plus `exit`, so the file reads
like a site map.

## Layers

**Infrastructure** — `SQL`, `Config`, `Session`, `Csrf`, `RateLimiter`,
`Firewall`, `WebCache`, `Mailer`, `Markdown`, `Slug`, `Url`, `i18n`,
`PageEngine`, `Audit`.

**Domain** — `Question`, `Answer`, `Comment`, `Tag`, `Vote`, `Karma`, `Badge`,
`Flag`, `Moderation`, `Notification`, `Subscription`, `Message`, `Revision`,
`User`, `Search`, `Statistics`, `Permission`.

**Entry points** — templates in `app/design/default/`, API classes in
`app/code/classes/API/`, background jobs in `app/code/classes/bots/`, and the
CLI runner `app/app.php`.

Domain classes never print anything and never read `$_POST`. Templates and API
classes do the talking.

## Database layer

```php
$db = new SQL(0);
$row  = $db->cmdrow('SELECT * FROM users WHERE id = "{0}" LIMIT 0,1', [$id]);
$rows = $db->cmdrows('SELECT * FROM questions WHERE tags LIKE "%{0}%"', [$tag]);
$id   = $db->Create("questions", ["title" => $title, "body_md" => $body]);
$db->Update("questions", ["score" => 5], $id);
```

Numbered placeholders are replaced with escaped values. **A placeholder must
always be written inside quotes.** For values that cannot be quoted use
`SQL::int()` (LIMIT, OFFSET) or `SQL::identifier()` (dynamic ORDER BY). The
driver is PDO, so the same code runs on any hoster that can talk to MySQL.

## Lazy domain objects

```php
$question = new Question(42);   // no query yet
echo $question->title;          // one query, whole row cached
$question->refresh();           // forget the cache
```

Lists use `User::loadMany()` and `Comment::forPosts()` to avoid the N+1
queries that a lazy loader invites.

## Templates

`.php` files under `app/design/<skin>/`, rendered with
`PageEngine::html("page_questions", ["tag" => "php"])`; the array arrives as
`$params`. A skin registered with a higher priority can override single
templates without touching the originals.

Translations live next to the template as `page_questions.i18n.json`
(`{"en": {...}, "de": {...}}`) and are loaded with `i18n::init(__FILE__)`.
Shared strings live in `app/locale/core.i18n.json`. The lookup key is the
English source string, so a missing translation degrades to English.

## Vue islands

Vue 3 is loaded from a CDN as the global build. A page mounts one small app on
a container it owns:

```php
<div id="questionApp" v-cloak> … </div>
<script>
const { createApp } = Vue;
createApp({
  data() { return Object.assign(<?= json_encode($vueConfig) ?>, { busy: false }); },
  methods: { async vote(type, id, value) { … await askbot.api("vote.cast", …) } }
}).mount("#questionApp");
</script>
```

Two rules that keep this safe:

* Server rendered post bodies carry `v-pre` so a `{{ … }}` inside a code sample
  is never interpreted as a Vue expression.
* All writes go through `askbot.api()`, which attaches the CSRF token and
  unwraps the response envelope.

## Security model

| Risk | Answer |
|---|---|
| Stored XSS | post bodies are HTML escaped *before* Markdown rendering; the renderer only emits tags it generated itself |
| Dangerous links | scheme allow list (`http`, `https`, `mailto`, `ftp`) |
| SQL injection | escaped placeholders, ints cast explicitly |
| CSRF | token on every write; the API treats anything not on the read allow list as a write |
| Session theft | strict mode, http only, SameSite, id rotation on login and hourly |
| Brute force | per IP and per account rate limits on sign in, sign up, posting, voting |
| Privacy | IP addresses are stored as salted hashes only |
| Uploads | verified by content, re-encoded, random names, no execution |

## Background jobs

One class per job in `app/code/classes/bots/`, each with a static `run()` that
returns a short status line. `CLI::tasks()` discovers them from the directory,
so adding a job means adding a file.

## Caching

`WebCache` uses APCu when it is loaded and falls back to files under
`logs/cache`. It is used for tag clouds and site statistics — never for
anything user specific.
