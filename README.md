# askbot_php

A self-hosted question and answer community in plain PHP. Ask a question, get
answers, vote on what helps, and let reputation open up the site step by step.

No framework, no build step, no runtime dependencies: PHP 8.2+, MariaDB/MySQL,
and a browser. Vue 3 and Bootstrap 5 are loaded from a CDN and used as small
islands on top of server rendered HTML, so every page works (and is indexable)
without JavaScript.

```
docker compose up -d
open http://localhost:8080
```

The first account you register becomes the administrator.

---

## What it does

**Questions and answers**
Markdown posts with live preview, code blocks and image upload · up and down
votes · accepted answers · comments · tags with synonyms and their own wiki
pages · full revision history with diffs · duplicates, closing and reopening ·
bounties · favourites · full text search with operators
(`tag:php user:12 answers:0 is:accepted score:5`).

**Reputation**
Karma for upvotes and accepted answers, with a daily cap and a complete audit
trail on every profile. Karma thresholds unlock commenting, voting, flagging,
editing other people's posts, tag wikis and close votes — every threshold is
configurable in the admin area.

**Badges**
32 achievements in bronze, silver and gold. Cheap checks run inline, expensive
ones in a background job.

**Moderation**
Flag queue, community close/reopen/delete votes, automatic hiding after enough
flags, suspensions, role management and an append-only audit log.

**Accounts**
Argon2id passwords (bcrypt fallback), email verification, password reset,
TOTP two factor authentication, "remember me", optional OAuth2 sign in with
Google, GitHub or Discord, and personal API keys.

**Notifications**
In-app notification feed, per-user email digests (daily or weekly), question
and tag subscriptions, @mentions, and private messages. Mail is queued and
delivered by a background job, so a slow SMTP server never blocks a request.

**Machine readable**
A versioned JSON API for everything the UI can do (`/api/question.list.json`),
RSS feeds, sitemap.xml, OpenSearch, a web manifest and schema.org QAPage
markup on every question.

**Operations**
Docker Compose stack, a CLI runner for background jobs, a health page, a
statistics dashboard, rate limiting, CSRF protection, security headers, and an
importer for the old 2013 database.

---

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer, with `pdo_mysql`, `mbstring`, `json`, `gd` |
| Database | MariaDB 10.6+ or MySQL 8.0+ |
| Web server | Apache with `mod_rewrite`, nginx, or `php -S` for development |
| Optional | `apcu` for caching, an SMTP server for outgoing mail |

There are no Composer packages at runtime. Composer is only used for PHPUnit.

---

## Installation

### Docker (recommended)

```bash
git clone https://github.com/andreaskasper/askbot_php.git
cd askbot_php
cp .env.example .env          # adjust APP_SECRET at least
docker compose up -d
```

* Site: <http://localhost:8080>
* Mailpit (all outgoing mail): <http://localhost:8025>
* Database: `localhost:3306`, user `askbot`

The schema and the badge catalogue are imported automatically on the first
start. Sample content:

```bash
docker compose exec web php html/app/app.php demo
```

### Manual installation

1. Point the document root of a virtual host at `html/`.
2. Create an empty UTF-8 database.
3. Open the site in a browser — the installer asks for the connection details,
   imports the schema and writes `html/app/config.php`.
4. Register the first account; it becomes the administrator.

Instead of the installer you can set the environment variables
`MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DB`,
`BASE_URL` and `APP_SECRET` — the installer then never appears.

### Development server

```bash
export MYSQL_HOST=127.0.0.1 MYSQL_USER=root MYSQL_PASSWORD=secret MYSQL_DB=askbot
export BASE_URL=http://localhost:8080 APP_SECRET=dev-secret STAGE=development
php html/app/app.php migrate      # import database.sql + database.seed.sql
php html/app/app.php demo         # optional sample content
php -S localhost:8080 -t html html/router.php
```

---

## Background jobs

Either run the `cron` container from the compose file, or install `crontab`
from the repository root:

```bash
php html/app/app.php cron --loop --sleep=60   # everything, forever
php html/app/app.php bot -t mailer            # one job, once
```

| Job | What it does | Suggested interval |
|---|---|---|
| `mailer` | delivers the mail queue | every minute |
| `notifications` | turns unread notifications into digest mail | every 5 minutes |
| `badges` | awards view, streak and score based badges | every 10 minutes |
| `maintenance` | rebuilds counters, expires bounties | daily |
| `cleanup` | removes expired tokens, old logs, stale registrations | daily |
| `digest` | weekly "from your tags" mail | daily |
| `sitemap` | writes a static sitemap.xml | daily |

Other useful commands:

```bash
php html/app/app.php admin you@example.com    # promote an account
php html/app/app.php verify you@example.com   # confirm an address by hand
php html/app/app.php migrate                  # create or update the schema
```

---

## Migrating from the old version

The 2013 release stored data in a different schema. `migrate_legacy.php`
copies users, questions, answers, comments, tags, votes, karma and private
messages into the new one:

```bash
php migrate_legacy.php --host=localhost --user=root --password=secret \
    --database=old_askbot --dry-run     # look first
php migrate_legacy.php --host=localhost --user=root --password=secret \
    --database=old_askbot               # then do it
```

Old md5 password hashes are carried over and transparently upgraded to
Argon2id the first time each member signs in.

---

## The JSON API

Every endpoint follows `/api/<namespace>.<method>.<format>`:

```bash
curl "https://example.com/api/question.list.json?tag=php&sort=votes"
curl -H "Authorization: Bearer <api key>" \
     -d "title=…" -d "body=…" -d "tags[]=php" \
     "https://example.com/api/question.create.json"
```

```json
{
  "result": { "questions": [ … ], "pagination": { "page": 1, "pages": 12 } },
  "err": { "id": 0, "msg": "" },
  "runtime": 0.021,
  "timestamp": { "unix": 1786500000, "iso8601": "2026-08-12T10:00:00+00:00" }
}
```

`err.id` is `0` on success and mirrors the HTTP status otherwise. Reads are
open to everyone (unless the site is private), writes need either a session
plus CSRF token or a personal API key from the account settings.

The full list lives in [docs/API.md](docs/API.md) and as an OpenAPI document in
[docs/openapi.yaml](docs/openapi.yaml).

---

## Layout

```
docker-compose.yml        the whole stack: web, database, mail
database.sql              schema (InnoDB, utf8mb4, foreign keys)
database.seed.sql         badge catalogue and default settings
migrate_legacy.php        importer for the 2013 database
crontab                   background jobs for a classic server
scripts/                  smoke and write path tests
tests/                    PHPUnit unit and functional tests
html/                     document root
  index.php               single entry point
  router.php              only for php -S
  app/
    app.php               command line runner
    code/classes/         domain and infrastructure classes
      API/                one file per API namespace
      bots/               background jobs
      web/Routing.php     the URL map
    design/default/       templates (.php) with sibling *.i18n.json
    locale/               shared translations
  skins/default/          css, js, images
```

Conventions and design decisions: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md).

---

## Security

* Post bodies are HTML escaped **before** Markdown rendering, so raw HTML in a
  post is never executed — there is no sanitiser to bypass.
* Link schemes are checked against an allow list (`javascript:`, `data:` and
  friends are dropped).
* Argon2id password hashing with a bcrypt fallback, TOTP two factor.
* CSRF tokens on every state changing request; the API treats any method that
  is not on the read allow list as a write.
* Rate limits per IP and per account on sign in, sign up, posting, voting,
  flagging, uploads and the API.
* IP addresses are only stored as salted hashes.
* Uploads are verified by content, re-encoded to strip metadata, and stored
  outside the code directory.
* Security headers are sent by the application and by the shipped vhost.

Found something? Please open a security advisory on GitHub rather than a
public issue.

---

## Testing

```bash
composer install
vendor/bin/phpunit                              # 41 tests
bash scripts/smoke_test.sh http://localhost:8080       # every page and endpoint
bash scripts/write_path_test.sh http://localhost:8080  # sign up, ask, answer, vote
```

CI runs the linter on PHP 8.2, 8.3 and 8.4, the unit and functional tests
against MariaDB 11.4, and the smoke test against a live server.

---

## Roadmap

See [ROADMAP.md](ROADMAP.md) for what is planned next and what was deliberately
left out.

## License

FastFood Minimal License (FFM) — see [LICENSE](LICENSE). It is MIT in
substance, with a friendly, entirely optional gratitude clause.
