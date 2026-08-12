# Roadmap

What is done, what is next, and what was left out on purpose.

## Done (1.0)

- [x] Complete rewrite for PHP 8.2+ (the old code stopped working with PHP 7)
- [x] InnoDB schema with foreign keys, utf8mb4 and full text indexes
- [x] Questions, answers, comments, votes, accepted answers, favourites
- [x] Tags with wikis and synonyms, revision history with diffs
- [x] Karma with a daily cap, configurable thresholds and a full audit trail
- [x] 32 badges, awarded inline and by a background job
- [x] Flags, community close votes, suspensions, moderator queue
- [x] Markdown editor with preview, image upload, drafts in local storage
- [x] Full text search with operators and search suggestions
- [x] Argon2id passwords, email verification, password reset, TOTP 2FA, OAuth2
- [x] Notifications, subscriptions, private messages, mail queue and digests
- [x] JSON API for everything, with API keys and rate limits
- [x] Admin area: settings, users, moderation, statistics, health
- [x] German and English UI, RSS, sitemap, OpenSearch, schema.org markup
- [x] Docker Compose stack, background job runner, CI, tests, smoke tests
- [x] Importer for the 2013 database

## Next

**Content**
- [ ] Bounties end to end (the schema and the expiry job exist, the UI does not)
- [ ] Merge duplicate questions instead of only linking them
- [ ] Draft questions and scheduled publishing
- [ ] Code syntax highlighting (highlight.js as an optional include)
- [ ] Optional LaTeX rendering for maths heavy communities

**Community**
- [ ] Review queues in the style of "first posts" and "late answers"
- [ ] Trusted user privileges (edit without review, retag)
- [ ] Weekly "top contributors" mail
- [ ] User blocking and per-tag muting

**Search**
- [ ] Optional Meilisearch or Typesense backend for larger installations
- [ ] "Related questions" from a proper similarity score instead of MATCH
- [ ] Saved searches with mail alerts

**Platform**
- [ ] Translation coverage: 397 of 501 UI strings are translated to German
- [ ] More languages, ideally through a translation platform
- [ ] Webhooks for new questions and answers
- [ ] Single sign on via OIDC for company installations
- [ ] Import and export in the Stack Exchange data dump format
- [ ] Progressive web app with push notifications
- [ ] Accessibility pass with a screen reader

**Operations**
- [ ] Structured logging with a request id
- [ ] Prometheus metrics endpoint
- [ ] Backup and restore command
- [ ] Optional Redis session and cache backend

## Deliberately not planned

- **A framework.** The point of this project is that it runs anywhere with a
  copy of the files.
- **A build step for the frontend.** Vue islands from a CDN keep deployment to
  "upload the files".
- **A plugin system with hooks everywhere.** Skins and the API cover the
  realistic extension points; a hook system would age badly.
- **Comment threading.** Comments are for clarification, answers are for
  answers.
