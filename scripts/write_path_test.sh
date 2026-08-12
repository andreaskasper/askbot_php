#!/usr/bin/env bash
#
# write_path_test.sh - exercise the paths that change data: sign up, sign in,
# ask, answer, comment, vote, accept, search. Needs a running site.
#
#   bash scripts/write_path_test.sh http://localhost:8080
#
set -u
BASE="${1:-http://localhost:8080}"
JAR="$(mktemp)"
FAILED=0
SUFFIX="$RANDOM$RANDOM"
trap 'rm -f "$JAR"' EXIT

say()  { echo "  $*"; }
fail() { echo "  FAIL  $*"; FAILED=$((FAILED+1)); }

csrf() { curl -s -b "$JAR" -c "$JAR" "$BASE/api/site.info.json" > /dev/null
         curl -s -b "$JAR" -c "$JAR" "$BASE/account/signin" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | cut -d'"' -f4; }

echo "Write path test on $BASE"

# --- sign up ---------------------------------------------------------------
TOKEN="$(csrf)"
[ -n "$TOKEN" ] || fail "no CSRF token in the sign in form"
STATUS=$(curl -s -o /tmp/wp_body -w '%{http_code}' -b "$JAR" -c "$JAR" -X POST "$BASE/account/signup" \
  --data-urlencode "csrf_token=$TOKEN" \
  --data-urlencode "username=tester$SUFFIX" \
  --data-urlencode "email=tester$SUFFIX@example.com" \
  --data-urlencode "password=a-very-long-password" \
  --data-urlencode "password_repeat=a-very-long-password")
if [ "$STATUS" = "200" ] || [ "$STATUS" = "302" ]; then say "ok    sign up"; else fail "sign up -> $STATUS"; fi

# --- confirm the address ----------------------------------------------------
# The site requires a verified email address; in a test run we confirm it with
# the CLI helper instead of clicking the link in the mail.
if [ -x "$(command -v php)" ] && [ -f html/app/app.php ]; then
    php html/app/app.php verify "tester$SUFFIX@example.com" > /dev/null 2>&1 \
        && say "ok    email confirmed (CLI)" \
        || say "note  could not confirm the address from the CLI"
fi

# --- sign in ---------------------------------------------------------------
TOKEN="$(csrf)"
curl -s -o /dev/null -b "$JAR" -c "$JAR" -X POST "$BASE/account/signin" \
  --data-urlencode "csrf_token=$TOKEN" \
  --data-urlencode "login=tester$SUFFIX" \
  --data-urlencode "password=a-very-long-password"

ME="$(curl -s -b "$JAR" -c "$JAR" "$BASE/api/user.me.json")"
if echo "$ME" | grep -q '"username"'; then say "ok    sign in"; else fail "sign in: $ME"; fi
APITOKEN="$(echo "$ME" | grep -o '"csrf_token":"[^"]*"' | cut -d'"' -f4)"

api_post() { # api_post <endpoint> <curl data args...>
    local endpoint="$1"; shift
    curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/api/$endpoint.json" --data-urlencode "csrf_token=$APITOKEN" "$@"
}

# --- ask -------------------------------------------------------------------
OUT="$(api_post question.create \
  --data-urlencode "title=How does the write path test behave in practice?" \
  --data-urlencode "body=This question is created by the automated write path test to make sure the whole chain works." \
  --data-urlencode "tags[]=testing" --data-urlencode "tags[]=automation")"
QID=""
if echo "$OUT" | grep -q '"err":{"id":0'; then
    QID="$(echo "$OUT" | grep -o '"question":{"id":[0-9]*' | grep -o '[0-9]*$')"
fi
if [ -n "$QID" ]; then say "ok    ask question (#$QID)"; else fail "ask: $OUT"; fi

# --- answer ----------------------------------------------------------------
OUT="$(api_post answer.create --data-urlencode "question_id=$QID" \
  --data-urlencode "body=Here is an answer that is long enough to pass the validation rules of this site.")"
AID=""
if echo "$OUT" | grep -q '"err":{"id":0'; then
    AID="$(echo "$OUT" | grep -o '"answer":{"id":[0-9]*' | grep -o '[0-9]*$')"
fi
if [ -n "$AID" ]; then say "ok    post answer (#$AID)"; else fail "answer: $OUT"; fi

# --- comment ---------------------------------------------------------------
OUT="$(api_post comment.create --data-urlencode "post_type=question" --data-urlencode "post_id=$QID" \
  --data-urlencode "body=A clarifying comment from the test suite.")"
echo "$OUT" | grep -q '"body_html"' && say "ok    comment" || fail "comment: $OUT"

# --- self vote must be rejected --------------------------------------------
OUT="$(api_post vote.cast --data-urlencode "post_type=question" --data-urlencode "post_id=$QID" --data-urlencode "value=1")"
echo "$OUT" | grep -qi "own post" && say "ok    self vote rejected" || fail "self vote was not rejected: $OUT"

# --- accept ----------------------------------------------------------------
OUT="$(api_post question.accept --data-urlencode "id=$QID" --data-urlencode "answer_id=$AID")"
echo "$OUT" | grep -q '"accepted":true' && say "ok    accept answer" || fail "accept: $OUT"

# --- favourite and subscribe ------------------------------------------------
OUT="$(api_post question.favorite --data-urlencode "id=$QID")"
echo "$OUT" | grep -q '"is_favorite":true' && say "ok    favourite" || fail "favourite: $OUT"

# --- markdown preview -------------------------------------------------------
OUT="$(api_post site.preview --data-urlencode 'body=**bold** <script>alert(1)</script>')"
if echo "$OUT" | grep -q "strong" && ! echo "$OUT" | grep -q "<script>"; then
    say "ok    markdown preview escapes html"
else
    fail "preview: $OUT"
fi

# --- CSRF protection --------------------------------------------------------
OUT="$(curl -s -b "$JAR" -c "$JAR" -X POST "$BASE/api/comment.create.json" \
  --data-urlencode "post_type=question" --data-urlencode "post_id=$QID" --data-urlencode "body=no token here")"
echo "$OUT" | grep -qi "csrf" && say "ok    CSRF token enforced" || fail "CSRF not enforced: $OUT"

# --- the question renders ---------------------------------------------------
STATUS=$(curl -s -o /tmp/wp_body -w '%{http_code}' "$BASE/api/question.get.json?id=$QID")
if [ "$STATUS" = "200" ] && grep -q '"answers"' /tmp/wp_body; then say "ok    question renders with answers"; else fail "question.get -> $STATUS"; fi

# --- search finds it --------------------------------------------------------
sleep 1
OUT="$(curl -s "$BASE/api/search.query.json?q=write+path+test")"
echo "$OUT" | grep -q '"questions"' && say "ok    search responds" || fail "search: $OUT"

# --- sign out ---------------------------------------------------------------
curl -s -o /dev/null -b "$JAR" -c "$JAR" "$BASE/account/signout"
OUT="$(curl -s -b "$JAR" -c "$JAR" "$BASE/api/user.me.json")"
echo "$OUT" | grep -q '"id":0' && fail "still signed in" || say "ok    sign out"

echo
[ "$FAILED" -eq 0 ] && echo "All write path checks passed." || echo "$FAILED check(s) failed."
exit "$FAILED"
