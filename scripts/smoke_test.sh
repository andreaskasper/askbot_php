#!/usr/bin/env bash
#
# smoke_test.sh - hit every important URL once and fail on the first problem.
#
#   bash scripts/smoke_test.sh http://localhost:8080
#
set -u
BASE="${1:-http://localhost:8080}"
FAILED=0
COOKIES="$(mktemp)"
trap 'rm -f "$COOKIES"' EXIT

check() { # check <expected-status> <path> [grep-pattern]
    local expected="$1" path="$2" pattern="${3:-}"
    local body status
    body="$(curl -s -o /tmp/smoke_body -w '%{http_code}' -b "$COOKIES" -c "$COOKIES" "$BASE$path")"
    status="$body"
    if [ "$status" != "$expected" ]; then
        echo "  FAIL  $path -> $status (expected $expected)"
        head -c 400 /tmp/smoke_body | sed 's/^/        /'
        FAILED=$((FAILED+1))
        return
    fi
    if [ -n "$pattern" ] && ! grep -qi -- "$pattern" /tmp/smoke_body; then
        echo "  FAIL  $path -> missing '$pattern'"
        FAILED=$((FAILED+1))
        return
    fi
    echo "  ok    $path"
}

check_no_errors() { # the app must never leak a PHP warning into the page
    if grep -qiE "(Fatal error|Parse error|Warning:|Notice:|Deprecated:)" /tmp/smoke_body; then
        echo "  FAIL  PHP diagnostics leaked into the response"
        FAILED=$((FAILED+1))
    fi
}

echo "Smoke testing $BASE"
echo "pages"
check 200 "/"                    "<html"
check_no_errors
check 200 "/questions"           "questions"
check 200 "/questions?sort=votes"
check 200 "/questions?scope=unanswered"
check 200 "/tags"
check 200 "/users"
check 200 "/badges"
check 200 "/search?q=php"
check 200 "/about"
check 200 "/faq"
check 200 "/help"
check 200 "/privacy"
check 200 "/terms"
check 200 "/account/signin"      "form"
check 200 "/account/signup"
check 200 "/account/recover"
check 404 "/this-page-does-not-exist"

echo "machine readable"
check 200 "/feeds/questions.rss" "<rss"
check 200 "/feeds/answers.rss"   "<rss"
check 200 "/sitemap.xml"         "<urlset"
check 200 "/robots.txt"          "User-agent"
check 200 "/opensearch.xml"      "OpenSearch"
check 200 "/manifest.webmanifest"

echo "api"
check 200 "/api/site.info.json"        '"version"'
check 200 "/api/site.stats.json"       '"questions"'
check 200 "/api/question.list.json"    '"pagination"'
check 200 "/api/tag.list.json"         '"tags"'
check 200 "/api/user.list.json"        '"users"'
check 200 "/api/badge.list.json"       '"badges"'
check 200 "/api/search.query.json?q=a" '"questions"'
check 404 "/api/nope.nope.json"        '"msg"'
check 401 "/api/user.me.json"          '"msg"'

echo "protected areas"
check 302 "/questions/ask"
check 403 "/admin/settings"
check 405 "/api/question.create.json"

echo "content"
QID="$(curl -s "$BASE/api/question.list.json?per_page=1" | grep -o '"id":[0-9]*' | head -1 | cut -d: -f2)"
if [ -n "${QID:-}" ]; then
    check 200 "/api/question.get.json?id=$QID" '"answers"'
    SLUG="$(curl -s "$BASE/api/question.get.json?id=$QID" | grep -o '"url":"[^"]*"' | head -1 | sed 's|.*/||; s|"||')"
    check 200 "/question/$QID/$SLUG" "post-body"
    check_no_errors
else
    echo "  skip  no questions in the database"
fi

echo
if [ "$FAILED" -eq 0 ]; then
    echo "All checks passed."
else
    echo "$FAILED check(s) failed."
fi
exit "$FAILED"
