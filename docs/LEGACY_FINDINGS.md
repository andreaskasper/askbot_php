# What was wrong with the old code

Notes from reading the 2013/2016 version before the rewrite. Kept because the
same mistakes are easy to repeat, and because it explains why a rewrite was
cheaper than a repair.

## It could not run at all

* The whole database layer used `mysql_*`, removed in PHP 7 (2015).
  Every page died on the first query on any supported PHP version.
* `class SQL { function SQL(...) }` — PHP 4 style constructors, removed in
  PHP 8.
* `public static function init($ConnNr = 0, $DBuri)` — a required parameter
  after an optional one; fatal since PHP 8.
* `new Thread(...)` in `cmdBackground()` referenced a pecl extension that was
  never a dependency.

## Security

* Passwords were `md5($password)`, unsalted. The importer keeps those hashes so
  nobody is locked out, and replaces them with Argon2id on the first sign in.
* `$_REQUEST = array_merge($_GET, $_POST)` was rebuilt globally, so any GET
  parameter could stand in for a POST field. No CSRF token existed anywhere,
  which made every state changing URL a one-click attack.
* The session class hashed only `PwdSalt . User-Agent` and wrote session data
  with string concatenated SQL.
* Post bodies went through a BBCode parser and were printed unescaped in
  several templates.
* Login providers included OpenID 2.0 (dead since 2015) and the Facebook PHP
  SDK of 2012; both were vendored copies with known issues.
* Registration generated a six character password with
  `substr($alpha, rand(0, strlen($alpha)), 1)` — note the off by one, which can
  return an empty string, silently shortening the password.

## Correctness

* `includes/routing.php`: the user routes fell through the `switch` without
  `break` or `exit`, so `/users/7/name/inbox` rendered the inbox, then the edit
  page, then the karma page, then the badges page into one response.
* The base path was derived with `substr($_SERVER["REQUEST_URI"], strlen($_SERVER["SCRIPT_NAME"]) - 10)`
  — the `10` is `strlen("/index.php")`, so any other entry point broke routing.
  (The rewrite hit the same class of problem with PHP's built in server and now
  only trusts `SCRIPT_NAME` when it really ends in `/index.php`.)
* `Question::PermalinkByData()` replaced umlauts with mojibake constants; the
  source file itself had already lost its encoding.
* Tables were MyISAM with `bigint` unix timestamps, no foreign keys and no
  transactions, so a failed vote left the counters wrong forever.
* `SELECT SQL_CALC_FOUND_ROWS` on every question list — deprecated in MySQL 8
  and slower than a second `COUNT(*)` on InnoDB.

## Maintainability

* Most source files used carriage returns as line endings, so every file was
  one line to `git diff` and code review was impossible.
* 11 MB of vendored third party code (CKEditor, jQuery, PHPMailer, Akismet,
  LightOpenID, fancybox, a full flag icon set) with no update path.
* `composer.json` declared no dependencies and no autoloader; `vendor/`
  contained only the composer stub.
* Business logic lived inside templates: `page_questions.php` built its SQL,
  paginated, formatted numbers and rendered HTML in the same file.
* Comments, class docs and error messages mixed German and English.
* No tests, no CI, no schema migrations.

## What was kept

The domain model was sound: questions, answers, comments, tags, votes, karma
log, badges, flags, private messages, tag synonyms and a per-user notification
table. The new schema is a cleaned up version of the same idea, which is why
the importer is a straight column mapping.
