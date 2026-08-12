# JSON API

```
/api/<namespace>.<method>.<format>
```

`format` is `json` (envelope), `jsonac` (result only), `txt` or `successcode`.
`/api/question/list/json` works as well as `/api/question.list.json`.

## Envelope

```json
{
  "result": { },
  "err": { "id": 0, "msg": "" },
  "runtime": 0.021,
  "timestamp": { "unix": 1786500000, "iso8601": "2026-08-12T10:00:00+00:00" }
}
```

`err.id` is `0` on success. On failure it mirrors the HTTP status
(400 validation, 401 sign in required, 403 forbidden or bad CSRF token,
404 unknown, 405 wrong method, 429 rate limited, 500 server error).

## Authentication

* **Session** — the cookie of a signed in browser. Writes additionally need the
  CSRF token as `csrf_token` in the body or `X-CSRF-Token` in a header.
* **API key** — `Authorization: Bearer <key>`, created in the account settings.
  Keys authenticate themselves, so no CSRF token is needed.

Every method that is not on the read allow list requires `POST`.

## Rate limits

600 reads and 120 writes per five minutes per account (or per IP for anonymous
callers), plus the specific limits for asking, answering, commenting, voting,
flagging and uploading.

## Endpoints

### question

| Method | Type | Parameters |
|---|---|---|
| `question.list` | read | `tag`, `q`, `scope` (`unanswered`, `unsolved`, `accepted`, `bounty`, `closed`), `sort` (`activity`, `newest`, `votes`, `answers`, `views`, `hot`), `user`, `page`, `per_page` |
| `question.get` | read | `id`, `sort` |
| `question.similar` | read | `title`, `exclude` |
| `question.create` | write | `title`, `body`, `tags[]` |
| `question.update` | write | `id`, `title`, `body`, `tags[]`, `comment` |
| `question.delete` | write | `id` |
| `question.accept` | write | `id`, `answer_id` |
| `question.close` | write | `id`, `action` (`close`, `reopen`, `delete`), `reason`, `duplicate_of` |
| `question.favorite` | write | `id` |
| `question.subscribe` | write | `id` |

### answer, comment, vote

| Method | Type | Parameters |
|---|---|---|
| `answer.list` | read | `question_id`, `sort` |
| `answer.create` | write | `question_id`, `body` |
| `answer.update` | write | `id`, `body`, `comment` |
| `answer.delete` | write | `id` |
| `comment.list` | read | `post_type`, `post_id` |
| `comment.create` | write | `post_type`, `post_id`, `body` |
| `comment.delete` | write | `id` |
| `vote.cast` | write | `post_type`, `post_id`, `value` (`1`, `-1`, or the same value again to retract) |

### tag, search, badge

| Method | Type | Parameters |
|---|---|---|
| `tag.list` | read | `sort` (`popular`, `name`, `newest`), `q`, `page` |
| `tag.suggest` | read | `q`, `limit` |
| `tag.get` | read | `name` |
| `tag.savewiki` | write | `name`, `description` |
| `tag.addsynonym` | write | `source`, `target` |
| `search.query` | read | `q` (supports `tag:`, `user:`, `answers:0`, `is:accepted`, `score:`), `page`, `sort` |
| `search.suggest` | read | `q` |
| `badge.list` / `badge.get` | read | `id` |

### user, notification, flag, site

| Method | Type | Parameters |
|---|---|---|
| `user.list` | read | `q`, `sort` (`karma`, `newest`, `active`, `name`), `page` |
| `user.get` | read | `id` |
| `user.me` | read | – |
| `user.activity` | read | `id` |
| `user.updateprofile` | write | `real_name`, `website`, `location`, `country`, `bio`, `locale`, `email_digest`, … |
| `user.setpassword` | write | `current_password`, `password`, `password_repeat` |
| `user.createapikey` | write | `label` |
| `user.sendmessage` | write | `to`, `subject`, `body` |
| `notification.list` | read | `limit`, `unread` |
| `notification.markread` | write | `id` (all when omitted) |
| `flag.create` | write | `post_type`, `post_id`, `reason`, `note` |
| `flag.handle` | write | `id`, `status` (moderators) |
| `site.info` / `site.stats` | read | – |
| `site.preview` | read | `body` — renders Markdown |
| `site.upload` | write | `file` (multipart) |

### admin (administrators and moderators)

`admin.settings`, `admin.setsettings`, `admin.queue`, `admin.suspenduser`,
`admin.unsuspenduser`, `admin.setrole`, `admin.recountkarma`,
`admin.statistics`.

## Examples

```bash
# newest PHP questions
curl "https://example.com/api/question.list.json?tag=php&sort=newest"

# ask a question with an API key
curl -H "Authorization: Bearer $ASKBOT_KEY" \
     -d "title=How do I keep sessions across nodes?" \
     -d "body=We run three web nodes and users get logged out at random." \
     -d "tags[]=php" -d "tags[]=sessions" \
     "https://example.com/api/question.create.json"

# result only, no envelope
curl "https://example.com/api/site.stats.jsonac"
```
