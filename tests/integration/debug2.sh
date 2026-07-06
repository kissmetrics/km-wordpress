#!/usr/bin/env bash
set -uo pipefail
cd "$(dirname "$0")"
DIR="$(cd ../../kissmetrics && pwd)"
URL="http://localhost:8089"
rm -rf ./kissmetrics && cp -r "$DIR" ./kissmetrics
docker compose down -v >/dev/null 2>&1
docker compose up -d db wp >/dev/null 2>&1
for i in $(seq 1 60); do docker compose exec -T wp test -f /var/www/html/wp-load.php >/dev/null 2>&1 && break; sleep 2; done
for i in $(seq 1 60); do [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/" 2>/dev/null)" != "000" ] && break; sleep 2; done
docker compose up -d cli >/dev/null 2>&1
WP(){ docker compose exec -e HOME=/tmp -T cli wp --allow-root "$@" 2>&1; }

WP core install --url="$URL" --title=KM --admin_user=admin --admin_password=admin123 --admin_email=t@e.com --skip-email >/dev/null 2>&1
WP plugin activate kissmetrics >/dev/null 2>&1
WP option update kissmetrics_key TESTKEY123 >/dev/null
WP option update kissmetrics_track_ecommerce 1 >/dev/null
WP plugin install woocommerce --activate >/dev/null 2>&1
WP transient delete --all >/dev/null 2>&1
for t in 1 2 3 4 5; do [ "$(WP plugin get woocommerce --field=status)" = "active" ] && break; sleep 2; done
echo "WC: $(WP plugin get woocommerce --field=status) $(WP plugin get woocommerce --field=version)"

echo "--- permalink = PLAIN (default) ---"
echo "permalink_structure=[$(WP option get permalink_structure)]"
ORDER=$(WP eval '$p=new WC_Product_Simple();$p->set_name("Widget");$p->set_regular_price("25");$p->set_status("publish");$pid=$p->save();$o=wc_create_order();$o->add_product(wc_get_product($pid),2);$o->set_billing_email("buyer@example.com");$o->calculate_totals();$o->update_status("completed");echo $o->get_id()."|".$o->get_checkout_order_received_url();' 2>/dev/null)
echo "order(plain) -> $ORDER"
U=$(echo "$ORDER" | cut -d'|' -f2)
echo "HEAD: $(curl -s -o /dev/null -w 'code=%{http_code} redirects=%{num_redirects} final=%{url_effective}' -L "$U")"
TY=$(curl -sL "$U"); echo "loader=$(grep -q scripts.kissmetrics.io <<<"$TY" && echo yes||echo no) purchased=$(grep -q Purchased <<<"$TY" && echo yes||echo no)"

echo "--- permalink = PRETTY (/%postname%/) ---"
WP rewrite structure '/%postname%/' --hard >/dev/null 2>&1
WP rewrite flush --hard >/dev/null 2>&1
echo "permalink_structure=[$(WP option get permalink_structure)]"
echo "htaccess present in web container: $(docker compose exec -T wp test -f /var/www/html/.htaccess && echo yes || echo no)"
ORDER2=$(WP eval '$o=wc_get_order(); $orders=wc_get_orders(array("limit"=>1)); $o=$orders[0]; echo $o->get_id()."|".$o->get_checkout_order_received_url();' 2>/dev/null)
echo "order(pretty url) -> $ORDER2"
U2=$(echo "$ORDER2" | cut -d'|' -f2)
echo "HEAD: $(curl -s -o /dev/null -w 'code=%{http_code} redirects=%{num_redirects} final=%{url_effective}' -L "$U2")"
TY2=$(curl -sL "$U2"); echo "loader=$(grep -q scripts.kissmetrics.io <<<"$TY2" && echo yes||echo no) purchased=$(grep -q Purchased <<<"$TY2" && echo yes||echo no)"
echo "snippet: $(grep -oE "_kmq.push\(\['record','Purchased',[^<]*" <<<"$TY2" | head -1)"

docker compose down -v >/dev/null 2>&1
