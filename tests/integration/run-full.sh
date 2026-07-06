#!/usr/bin/env bash
# Authoritative real-WordPress integration test for Kissmetrics plugin v0.3.0.
# Core events + WooCommerce (block checkout) purchase + auth + comments +
# link tracking + uninstall + official Plugin Check. Fresh stack, no stale state.
set -uo pipefail
cd "$(dirname "$0")"

DIR="$(cd ../../kissmetrics && pwd)"
URL="http://localhost:8089"
PASS=0; FAIL=0
ok(){ if eval "$2"; then echo "  PASS  $1"; PASS=$((PASS+1)); else echo "  FAIL  $1"; FAIL=$((FAIL+1)); fi; }

rm -rf ./kissmetrics && cp -r "$DIR" ./kissmetrics

echo "== Reset + start fresh stack =="
docker compose down -v >/dev/null 2>&1
docker compose up -d db wp >/dev/null 2>&1 || { echo "compose up failed"; exit 2; }
for i in $(seq 1 60); do docker compose exec -T wp test -f /var/www/html/wp-load.php >/dev/null 2>&1 && break; sleep 2; done
for i in $(seq 1 60); do [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/" 2>/dev/null)" != "000" ] && break; sleep 2; done
docker compose up -d cli >/dev/null 2>&1
WP(){ docker compose exec -e HOME=/tmp -T cli wp --allow-root "$@" 2>&1; }

echo "== Install WP + classic theme + permalinks + registration =="
WP core install --url="$URL" --title="KM Test" --admin_user=admin --admin_password=admin123 --admin_email=test@example.com --skip-email >/dev/null 2>&1
WP theme install twentytwentyone --activate >/dev/null 2>&1
WP rewrite structure '/%postname%/' --hard >/dev/null 2>&1
WP rewrite flush --hard >/dev/null 2>&1
WP option update users_can_register 1 >/dev/null 2>&1

echo "== Activate KM plugin + enable ALL options =="
echo "    $(WP plugin activate kissmetrics)"
WP option update kissmetrics_key TESTKEY123 >/dev/null
for opt in identify_users track_login track_signup_view track_signup track_views \
           track_links track_comment_links track_social track_search track_comment \
           identify_unregistered track_ecommerce; do
  WP option update "kissmetrics_${opt}" 1 >/dev/null
done

POST_ID=$(WP post create --post_title="Hello KM" --post_status=publish \
  --post_content='<p><a href="/about">internal</a> and <a href="https://google.com/x">external</a></p>' \
  --comment_status=open --porcelain 2>/dev/null)
echo "    seeded post id -> $POST_ID"

echo ""
echo "== A. Core front-end events =="
HOME=$(curl -s "$URL/")
ok "no PHP error on homepage"            '! grep -qiE "Fatal error|Warning:|Deprecated:|Notice:|Uncaught" <<<"$HOME"'
ok "KM loader + key on homepage"         'grep -q "scripts.kissmetrics.io" <<<"$HOME" && grep -q "TESTKEY123" <<<"$HOME"'
ok "Viewed Homepage event"               'grep -q "Viewed Homepage" <<<"$HOME"'
POST=$(curl -sL "$URL/?p=$POST_ID")
ok "Viewed post event on single"         'grep -q "Viewed post" <<<"$POST"'
ok "link tracking: internal trackClick"  'grep -q "trackClick" <<<"$POST"'
ok "link tracking: external outbound"    'grep -q "trackClickOnOutboundLink" <<<"$POST"'
ok "comment-submit tracking present"     'grep -q "'\''Commented'\''" <<<"$POST"'
SEARCH=$(curl -sL "$URL/?s=shoes")
ok "Searched Site event on search"       'grep -q "Searched Site" <<<"$SEARCH"'

echo ""
echo "== B. Auth pages =="
LOGIN=$(curl -s "$URL/wp-login.php")
ok "login page: KM loader present"       'grep -q "scripts.kissmetrics.io" <<<"$LOGIN"'
ok "login page: Signed in trackSubmit"   'grep -q "Signed in" <<<"$LOGIN"'
REG=$(curl -s "$URL/wp-login.php?action=register")
ok "register page: signup view event"    'grep -q "Viewed signup page" <<<"$REG"'
ok "register page: Created account event" 'grep -q "Created account" <<<"$REG"'

echo ""
echo "== C. WooCommerce purchase (block checkout) =="
WP plugin install woocommerce --activate >/dev/null 2>&1
WP transient delete --all >/dev/null 2>&1
WC_STATUS=""; for t in 1 2 3 4 5; do WC_STATUS=$(WP plugin get woocommerce --field=status); [ "$WC_STATUS" = "active" ] && break; sleep 2; done
# Flush rewrite rules AFTER WooCommerce registers its order-received endpoint
# (WooCommerce requires pretty permalinks; the order-received route 404s otherwise).
WP rewrite flush --hard >/dev/null 2>&1
echo "    woocommerce status -> $WC_STATUS ($(WP plugin get woocommerce --field=version))"
ORDER=$(WP eval '
  $p = new WC_Product_Simple(); $p->set_name("Test Widget"); $p->set_regular_price("25.00"); $p->set_status("publish"); $pid = $p->save();
  $o = wc_create_order(); $o->add_product(wc_get_product($pid), 2); $o->set_billing_email("buyer@example.com");
  $o->calculate_totals(); $o->update_status("completed");
  echo $o->get_id()."|".$o->get_total()."|".$o->get_checkout_order_received_url();
' 2>/dev/null)
echo "    order -> $(echo "$ORDER" | tr -d '\n')"
OR_URL=$(echo "$ORDER" | cut -d'|' -f3)
TY=$(curl -sL "$OR_URL")
BAD=$(curl -sL "$(echo "$OR_URL" | sed 's/key=.*/key=wrong-key/')")
ok "WooCommerce active"                   '[ "$WC_STATUS" = "active" ]'
ok "thankyou page renders, no fatal"      '! grep -qiE "Fatal error|Uncaught" <<<"$TY"'
ok "Purchased event on order-received"    'grep -q "Purchased" <<<"$TY"'
ok "purchase revenue (50) present"        'grep -q "\"Revenue\":50" <<<"$TY"'
ok "buyer identified by email"            'grep -q "buyer@example.com" <<<"$TY"'
ok "wrong order key emits NO purchase"    '! grep -q "Purchased" <<<"$BAD"'

echo ""
echo "== D. Uninstall cleanup =="
WP eval 'define("WP_UNINSTALL_PLUGIN", true); require "/var/www/html/wp-content/plugins/kissmetrics/uninstall.php";' >/dev/null 2>&1
LEFT=$(WP option get kissmetrics_key 2>&1)
ok "uninstall removes options"            '! [[ "$LEFT" == "TESTKEY123" ]]'

echo ""
echo "== E. Official Plugin Check (ERROR-level) =="
WP plugin install plugin-check --activate >/dev/null 2>&1
WP option update kissmetrics_key TESTKEY123 >/dev/null 2>&1
PC=$(WP plugin check kissmetrics --format=csv --fields=type,code,message 2>/dev/null)
PC_ERRORS=$(echo "$PC" | awk -F, 'NR>1 && $1=="ERROR"' | wc -l)
echo "    --- ERROR-level findings: $PC_ERRORS ---"
echo "$PC" | awk -F, 'NR>1 && $1=="ERROR"' | head -20
echo "    --- (sample of WARNING codes, informational) ---"
echo "$PC" | awk -F, 'NR>1 && $1=="WARNING"{print $2}' | sort | uniq -c | sort -rn | head -10
ok "Plugin Check: 0 ERROR-level findings" '[ "${PC_ERRORS:-1}" -eq 0 ]'

echo ""
echo "----------------------------------------"
echo "  $PASS passed, $FAIL failed"
echo "== Cleanup =="
docker compose down -v >/dev/null 2>&1
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
