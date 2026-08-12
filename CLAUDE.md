# Notes for AI assistants working on this repository

## What this is

A question and answer community in plain PHP 8.2+, no framework, no build step,
no runtime dependencies. `html/` is the document root, everything below
`html/app/` is application code that must never be reachable over HTTP.

## House rules

* **English** for code, comments, commit messages and documentation. The UI is
  translated through `*.i18n.json` files.
* **File names are PascalCase** for classes (`Question.php`), lower case for
  API and bot classes (`API/question.php`, `bots/mailer.php`), and
  `page_*.php` / `box_*.php` for templates.
* **Domain classes never print** and never read `$_POST`. Templates and API
  classes handle input and output.
* **Do not add a Composer runtime dependency** without a very good reason. The
  project must stay installable by copying files.

## Database access

```php
$db = new SQL(0);
$row = $db->cmdrow('SELECT * FROM users WHERE id = "{0}" LIMIT 0,1', [$id]);
$id  = $db->Create("questions", ["title" => $title]);
$db->Update("questions", ["score" => 5], $id);
```

Placeholders are numbered and **must be written inside quotes**. Never
concatenate a value into SQL. Use `SQL::int()` for LIMIT and
`SQL::identifier()` for a dynamic ORDER BY.

## Adding a page

1. `html/app/design/default/page_thing.php` — call `PageEngine::html("header", …)`
   at the top and `PageEngine::html("footer")` at the bottom.
2. Add a route in `html/app/code/classes/web/Routing.php`.
3. Put translatable strings in `__("…")` and add `page_thing.i18n.json` next to
   the template.

## Adding an API endpoint

1. `html/app/code/classes/API/thing.php`, `namespace API;`, class name lower
   case, all methods `public static function x(array $data): array`.
2. Return a plain array; `API::run()` adds the envelope.
3. Read only methods must be listed in `API::READ_METHODS`; everything else
   automatically requires POST plus a CSRF token.
4. Never reference a global class without a leading backslash inside
   `namespace API` — `\SQL`, `\MyUser`, `\API::need(...)`. A `use Question;`
   next to `class question` is a fatal error, because PHP class names are case
   insensitive.

## Adding a background job

`html/app/code/classes/bots/thing.php` with a static `run(): string`. It is
picked up automatically by `php html/app/app.php cron`.

## Before you commit

```bash
find html tests -name "*.php" -print0 | xargs -0 -n1 php -l   # syntax
vendor/bin/phpunit                                            # tests
bash scripts/smoke_test.sh http://localhost:8080              # pages and API
bash scripts/write_path_test.sh http://localhost:8080         # write paths
```

## Traps that already bit somebody

* `PageEngine` resolves templates from `$_ENV["basepath"] . "/design/"`, where
  `basepath` is `html/app`. Do not add another `/app/` to a path.
* Server rendered post bodies need `v-pre`, otherwise Vue interprets `{{ … }}`
  inside a code sample.
* `PASSWORD_ARGON2ID` does not exist in every PHP build — use
  `User::passwordAlgo()`.
* Permission checks that must work on the command line belong in `Permission`,
  not in `MyUser`.
