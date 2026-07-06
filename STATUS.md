# Kissmetrics WordPress plugin — v0.3.0 status

A hardened, WordPress.org-ready build derived from `kissmetrics_0.2.0/`. Originals were
left untouched; this is a parallel build folder.

## Done in this build (`kissmetrics_0.3.0/kissmetrics/`)

### Bug fixes
- **Outbound-link detection** — `$origin` was never imported into `parse_link()`, so every
  link classified as outbound. Now resolves the site host once via `home_url()`.
- **`get_currentuserinfo()`** (deprecated since WP 4.5) → `wp_get_current_user()`.
- **PHP 8 "undefined array key"** on the comment-author cookie — now guarded with `empty()`.
- **Block/FSE themes** — homepage & search detection moved from theme CSS body classes to
  server-side conditional tags (`is_front_page()`, `is_home()`, `is_search()`).

### Security / escaping
- All dynamic values escaped before output (`esc_js`, `wp_json_encode`, `esc_attr`).
- The XSS-prone unescaped `user_login` and cookie `comment_author_email` are now `esc_js`'d.
- All `$_POST` / `$_COOKIE` reads `wp_unslash`'d + sanitized.

### WordPress.org compliance
- Scripts emitted through the queue via `wp_add_inline_script` (registered inline-only handle).
- Direct-access guards (`ABSPATH`) on every PHP file.
- Full i18n: strings wrapped in `__()/esc_html__()/esc_html_e()` with the `kissmetrics` text domain.
- `uninstall.php` cleans up all `kissmetrics_*` options on delete.
- `readme.txt` refreshed: `Requires PHP`, `Requires at least: 6.3`, `Tested up to: 6.5`,
  matching `Stable tag`, GPLv3, full changelog/FAQ.
- `index.php` silence file + `license.txt`.

### New feature
- **WooCommerce purchase tracking** (opt-in) — records a `Purchased` event on the
  order-received page with revenue, currency, item names, item count, and order ID, and
  identifies the buyer by billing email. This is the option the km-ai-analyst onboarding
  copy already references but which previously did not exist.

Verified: all PHP files pass `php -l` on PHP 8.2.

## Verification (2026-06-25)
- Classes renamed `KM_Filter`/`KM_Admin` -> `Kissmetrics_Filter`/`Kissmetrics_Admin` (Plugin Check prefix warning).
- readme `Tested up to` bumped to 7.0 (Plugin Check ERROR `outdated_tested_upto_header`).
- **Unit smoke `tests/unit-smoke.php`: 18/18 PASS** (`php tests/unit-smoke.php`).
- **Live integration (WordPress + WooCommerce 10.9.1 block checkout):** core events, auth pages,
  uninstall, and WooCommerce purchase all verified. Rendered snippet confirmed:
  `_kmq.push(['record','Purchased',{"Order ID":11,"Revenue":50,"Currency":"USD","Item Count":1,"Items":["Widget"]}]);`
- **Plugin Check: 0 ERROR-level findings.**
- WooCommerce purchase is detected server-side via `is_order_received_page()` (block checkout does
  NOT fire `woocommerce_thankyou`), with order-key verification. Requires pretty permalinks (WC does too).

### Test gotchas (env, not plugin bugs)
1. OPcache in the `wordpress` image — `docker compose restart wp` after swapping the plugin file.
2. WooCommerce needs pretty permalinks; `wp rewrite flush --hard` AFTER WC is active or `order-received` 404s.
3. `tests/integration/run-full.sh` is flaky in section C (a `wp` call right after the WooCommerce
   install sometimes returns site HTML). `tests/integration/debug2.sh` is the known-good WooCommerce
   verifier. Hardening run-full.sh for one clean end-to-end green run is the next TODO.

## Still needs a human / out of code scope

1. **Run the official Plugin Check (PCP)** plugin against this build and clear any residual
   warnings — final gate before submission.
2. **Behavioral test** on a real WordPress (Docker harness in the chat) to confirm events
   land in Live View with `WP_DEBUG` clean.
3. **Directory assets** — `icon-256x256.png`, `banner-772x250.png`, and screenshots (binary,
   can't be generated here).
4. **WordPress.org submission** — register/own the `kissmetrics` plugin slug and upload via
   the SVN repo (`Publishing a new version` flow). Requires the wordpress.org committer account.
5. **Decide event-name convention** — plugin records `Viewed post`/`Viewed page`/`Viewed Homepage`;
   the rest of KM uses a canonical `Viewed Page` event. Align if the WP data should match.
6. **API-key auto-fetch via OAuth/account link** (from `install_options.md`) — a larger
   product change; still manual paste for now.
7. **Legacy social tracking** (FB/Twitter widgets) is mostly dead in 2026 — keep, drop, or replace.
