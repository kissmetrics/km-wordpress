#!/usr/bin/env bash
# Bring up (or re-provision) the standing Kissmetrics WordPress test site.
# Idempotent: safe to run repeatedly. Persists data in named volumes.
#
#   ./start.sh              # bring up + provision (WooCommerce NOT installed)
#   ./start.sh --woo        # also install + activate WooCommerce for purchase testing
#   KM_KEY=abc123 ./start.sh   # preload a real KM tracking key (else a placeholder)
#
set -uo pipefail
cd "$(dirname "$0")"

URL="http://localhost:8080"
KM_KEY="${KM_KEY:-PASTE_YOUR_REAL_KEY}"
WITH_WOO=0
[ "${1:-}" = "--woo" ] && WITH_WOO=1

echo "== Starting containers =="
docker compose up -d db wp >/dev/null 2>&1 || { echo "compose up failed"; exit 2; }
echo -n "   waiting for WordPress files"
for i in $(seq 1 60); do docker compose exec -T wp test -f /var/www/html/wp-load.php >/dev/null 2>&1 && break; echo -n .; sleep 2; done; echo
echo -n "   waiting for HTTP"
for i in $(seq 1 60); do [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/" 2>/dev/null)" != "000" ] && break; echo -n .; sleep 2; done; echo
docker compose up -d cli >/dev/null 2>&1
WP(){ docker compose exec -e HOME=/tmp -T cli wp --allow-root "$@" 2>&1; }

if WP core is-installed >/dev/null 2>&1; then
  echo "== WordPress already installed — reusing existing site =="
else
  echo "== Installing WordPress =="
  WP core install --url="$URL" --title="Kissmetrics Test Site" \
    --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email >/dev/null 2>&1
  WP theme install twentytwentyone --activate >/dev/null 2>&1
  WP rewrite structure '/%postname%/' --hard >/dev/null 2>&1
  WP rewrite flush --hard >/dev/null 2>&1
  WP option update users_can_register 1 >/dev/null 2>&1
  # Seed content so there are pages/posts to trigger events on.
  WP post create --post_title="Hello Kissmetrics" --post_status=publish --comment_status=open \
    --post_content='<p>A test post. <a href="/about">internal link</a> and <a href="https://example.org/">outbound link</a>.</p>' \
    --porcelain >/dev/null 2>&1
fi

echo "== Activating Kissmetrics plugin + enabling all events =="
WP plugin activate kissmetrics >/dev/null 2>&1
WP option update kissmetrics_key "$KM_KEY" >/dev/null 2>&1
for opt in identify_users track_login track_signup_view track_signup track_views \
           track_links track_comment_links track_social track_search track_comment \
           identify_unregistered; do
  WP option update "kissmetrics_${opt}" 1 >/dev/null 2>&1
done

if [ "$WITH_WOO" = "1" ]; then
  echo "== Installing WooCommerce (for purchase tracking) =="
  WP plugin install woocommerce --activate >/dev/null 2>&1
  # Settle: WC schedules background work on activation; wait until CLI is sane again.
  for t in $(seq 1 10); do [ "$(WP plugin get woocommerce --field=status 2>/dev/null)" = "active" ] && break; sleep 2; done
  WP option update kissmetrics_track_ecommerce 1 >/dev/null 2>&1

  # Minimal store config so browser checkout completes without payment setup:
  # a store address/currency, and Cash-on-Delivery enabled as a no-payment gateway.
  WP option update woocommerce_store_address "123 Test St" >/dev/null 2>&1
  WP option update woocommerce_default_country "US:CA" >/dev/null 2>&1
  WP option update woocommerce_currency "USD" >/dev/null 2>&1
  WP option update woocommerce_calc_taxes "no" >/dev/null 2>&1
  # Skip the onboarding wizard so /shop and checkout are immediately usable.
  WP option update woocommerce_onboarding_profile '{"skipped":true}' --format=json >/dev/null 2>&1
  WP option update woocommerce_task_list_hidden "yes" >/dev/null 2>&1
  WP option update woocommerce_cod_settings \
    '{"enabled":"yes","title":"Cash on delivery","description":"Pay with cash (test)","instructions":"Test order","enable_for_methods":[],"enable_for_virtual":"yes"}' \
    --format=json >/dev/null 2>&1

  # Re-flush AFTER WC registers its order-received endpoint (else it 404s).
  WP rewrite flush --hard >/dev/null 2>&1
  # A simple, purchasable, in-stock product to buy.
  if [ -z "$(WP eval 'echo count(wc_get_products(array("limit"=>1,"return"=>"ids"))) ? "x" : "";' 2>/dev/null)" ]; then
    WP eval '$p=new WC_Product_Simple();$p->set_name("Test Widget");$p->set_regular_price("25.00");$p->set_manage_stock(false);$p->set_stock_status("instock");$p->set_catalog_visibility("visible");$p->set_status("publish");$p->save();' >/dev/null 2>&1
  fi
  echo "   WooCommerce: $(WP plugin get woocommerce --field=status) $(WP plugin get woocommerce --field=version)"
fi

KEY_NOW="$(WP option get kissmetrics_key 2>/dev/null)"
echo ""
echo "========================================================"
echo "  Kissmetrics test site is UP"
echo "--------------------------------------------------------"
echo "  Site:        $URL"
echo "  Admin:       $URL/wp-admin   (admin / admin)"
echo "  KM settings: $URL/wp-admin/options-general.php?page=kissmetrics"
echo "  KM key set:  $KEY_NOW"
if [ "$KEY_NOW" = "PASTE_YOUR_REAL_KEY" ]; then
  echo ""
  echo "  >> Placeholder key in use. To see events in KM Live View, paste your"
  echo "     real key at the KM settings URL above (or re-run: KM_KEY=xxxx ./start.sh)."
fi
[ "$WITH_WOO" = "1" ] && echo "  Shop:        $URL/?post_type=product   (buy 'Test Widget' to fire Purchased)"
echo "========================================================"
