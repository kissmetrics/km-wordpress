#!/usr/bin/env bash
# Focused, robust verification of the two sections the big script flakes on:
# C = WooCommerce purchase (block checkout), E = official Plugin Check.
set -uo pipefail
cd "$(dirname "$0")"
DIR="$(cd ../../kissmetrics && pwd)"
URL="http://localhost:8089"
PASS=0; FAIL=0
ok(){ if eval "$2"; then echo "  PASS  $1"; PASS=$((PASS+1)); else echo "  FAIL  $1"; FAIL=$((FAIL+1)); fi; }

rm -rf ./kissmetrics && cp -r "$DIR" ./kissmetrics
docker compose down -v >/dev/null 2>&1
docker compose up -d db wp >/dev/null 2>&1
for i in $(seq 1 60); do docker compose exec -T wp test -f /var/www/html/wp-load.php >/dev/null 2>&1 && break; sleep 2; done
for i in $(seq 1 60); do [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/" 2>/dev/null)" != "000" ] && break; sleep 2; done
docker compose up -d cli >/dev/null 2>&1
WP(){ docker compose exec -e HOME=/tmp -T cli wp --allow-root "$@" 2>&1; }

WP core install --url="$URL" --title="KM Test" --admin_user=admin --admin_password=admin123 --admin_email=test@example.com --skip-email >/dev/null 2>&1
WP rewrite structure '/%postname%/' --hard >/dev/null 2>&1; WP rewrite flush --hard >/dev/null 2>&1
WP plugin activate kissmetrics >/dev/null 2>&1
WP option update kissmetrics_key TESTKEY123 >/dev/null
WP option update kissmetrics_track_ecommerce 1 >/dev/null

echo "== C. WooCommerce purchase (block checkout) =="
WP plugin install woocommerce --activate >/dev/null 2>&1
# WooCommerce sets an activation-redirect transient that collides with the next
# wp-cli bootstrap; clear it and let the scheduler settle before continuing.
WP transient delete --all >/dev/null 2>&1
WP option delete _transient__wc_activation_redirect >/dev/null 2>&1
sleep 3
WC_STATUS=""; for t in 1 2 3 4 5; do WC_STATUS=$(WP plugin get woocommerce --field=status); [ "$WC_STATUS" = "active" ] && break; sleep 2; done
echo "    woocommerce -> $WC_STATUS / $(WP plugin get woocommerce --field=version)"
# Flush WooCommerce's endpoint rewrite rules + clear OPcache in the web container
WP rewrite flush --hard >/dev/null 2>&1
docker compose restart wp >/dev/null 2>&1
for i in $(seq 1 30); do [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/" 2>/dev/null)" = "200" ] && break; sleep 2; done

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
echo "    [debug] loader present on order page: $(grep -q scripts.kissmetrics.io <<<"$TY" && echo yes || echo no)"
ok "WooCommerce active"                 '[ "$WC_STATUS" = "active" ]'
ok "thankyou renders, no fatal"         '! grep -qiE "Fatal error|Uncaught" <<<"$TY"'
ok "Purchased event present"            'grep -q "Purchased" <<<"$TY"'
ok "revenue (50) present"               'grep -q "\"Revenue\":50" <<<"$TY"'
ok "buyer identified by email"          'grep -q "buyer@example.com" <<<"$TY"'
ok "wrong key emits NO purchase"        '! grep -q "Purchased" <<<"$BAD"'
echo "    snippet: $(grep -oE "_kmq.push\(\['record','Purchased',[^<]*" <<<"$TY" | head -1)"

echo ""
echo "== E. Official Plugin Check =="
WP plugin install plugin-check --activate >/dev/null 2>&1
sleep 2
PC=$(WP plugin check kissmetrics --format=csv --fields=type,code,message 2>/dev/null)
# Guard: make sure we actually got CSV, not stray HTML
if grep -qi '<!doctype' <<<"$PC"; then echo "    (retrying plugin check...)"; sleep 3; PC=$(WP plugin check kissmetrics --format=csv --fields=type,code,message 2>/dev/null); fi
printf '%s\n' "$PC" > ./pc-out.csv   # raw dump for inspection on host
PC_ERRORS=$(echo "$PC" | awk -F, 'NR>1 && $1=="ERROR"' | wc -l)
echo "    ERROR-level findings: $PC_ERRORS"
echo "$PC" | awk -F, 'NR>1 && $1=="ERROR"{print "      ERROR "$2": "$3}' | head -20
echo "    WARNING codes (informational):"
echo "$PC" | awk -F, 'NR>1 && $1=="WARNING"{print $2}' | sort | uniq -c | sort -rn | head -12
ok "Plugin Check ran (got CSV, not HTML)" '! grep -qi "<!doctype" <<<"$PC"'
ok "Plugin Check: 0 ERROR-level findings" '[ "${PC_ERRORS:-1}" -eq 0 ]'

echo ""
echo "  $PASS passed, $FAIL failed"
docker compose down -v >/dev/null 2>&1
exit $([ "$FAIL" -eq 0 ] && echo 0 || echo 1)
