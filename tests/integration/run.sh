#!/usr/bin/env bash
# Real-WordPress integration smoke test for the Kissmetrics plugin v0.3.0.
set -uo pipefail
cd "$(dirname "$0")"

DIR="$(cd ../../kissmetrics && pwd)"
URL="http://localhost:8089"
PASS=0; FAIL=0
ok(){ if eval "$2"; then echo "  PASS  $1"; PASS=$((PASS+1)); else echo "  FAIL  $1"; FAIL=$((FAIL+1)); fi; }

# Fresh plugin copy next to the compose file (mounted into both wp + cli)
rm -rf ./kissmetrics && cp -r "$DIR" ./kissmetrics

echo "== Tearing down any previous run =="
docker compose down -v >/dev/null 2>&1

echo "== Starting MariaDB + WordPress (pulling images if needed) =="
docker compose up -d db wp || { echo "compose up failed"; exit 2; }

echo "== Waiting for WordPress core files to populate =="
for i in $(seq 1 60); do
  docker compose exec -T wp test -f /var/www/html/wp-load.php >/dev/null 2>&1 && break
  sleep 2
done

echo "== Waiting for HTTP to answer =="
for i in $(seq 1 60); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "$URL/" 2>/dev/null)
  [ "$code" != "000" ] && break
  sleep 2
done

echo "== Starting wp-cli helper =="
docker compose up -d cli >/dev/null 2>&1
WP(){ docker compose exec -T cli wp --allow-root "$@" 2>&1; }

echo "== Installing WordPress =="
WP core install --url="$URL" --title="KM Test" \
  --admin_user=admin --admin_password=admin123 \
  --admin_email=test@example.com --skip-email >/dev/null 2>&1

echo "== Activating plugin + configuring =="
ACT=$(WP plugin activate kissmetrics)
echo "    plugin activate -> $ACT"
WP option update kissmetrics_key TESTKEY123 >/dev/null
WP option update kissmetrics_track_views 1 >/dev/null
WP option update kissmetrics_track_search 1 >/dev/null
POST_ID=$(WP post create --post_title="Hello KM" --post_status=publish --post_content="<p>hi</p>" --porcelain)
echo "    created post id -> $POST_ID"

echo ""
echo "== Assertions =="
STATUS=$(WP plugin get kissmetrics --field=status)
ok "plugin reports active"                 '[ "$STATUS" = "active" ]'

HOME_HTML=$(curl -s "$URL/")
ok "no PHP fatal/warning on homepage"       '! grep -qiE "Fatal error|Warning:|Deprecated:|Notice:" <<<"$HOME_HTML"'
ok "KM loader present on homepage"          'grep -q "scripts.kissmetrics.io" <<<"$HOME_HTML"'
ok "API key injected"                       'grep -q "TESTKEY123" <<<"$HOME_HTML"'
ok "Viewed Homepage event present"          'grep -q "Viewed Homepage" <<<"$HOME_HTML"'

POST_HTML=$(curl -sL "$URL/?p=$POST_ID")
ok "Viewed post event on single post"       'grep -q "Viewed post" <<<"$POST_HTML"'

SEARCH_HTML=$(curl -sL "$URL/?s=shoes")
ok "Searched Site event on search page"     'grep -q "Searched Site" <<<"$SEARCH_HTML"'

# Load + render the admin settings screen (the plugin only loads admin.php under is_admin()).
ADMIN_HTML=$(WP eval 'require "/var/www/html/wp-content/plugins/kissmetrics/admin.php"; ob_start(); KM_Admin::config_page(); echo ob_get_clean();' 2>&1)
ok "admin settings screen renders, no fatal" '! grep -qiE "Fatal error|Uncaught|Parse error" <<<"$ADMIN_HTML"'
ok "settings screen shows API Key field"     'grep -q "API Key" <<<"$ADMIN_HTML"'
ok "settings screen shows WooCommerce option" 'grep -qi "WooCommerce" <<<"$ADMIN_HTML"'

echo ""
echo "----------------------------------------"
echo "  $PASS passed, $FAIL failed"
echo "== Cleaning up =="
docker compose down -v >/dev/null 2>&1
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
