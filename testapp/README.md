# Kissmetrics plugin — local test WordPress site

A standing WordPress site (Docker) for manually testing the Kissmetrics plugin in a
real browser. The plugin folder (`../kissmetrics`) is live-mounted, so it's the exact
code that would ship.

## Requirements
- Docker + Docker Compose (already present on this machine).

## Start

```bash
cd kissmetrics_0.3.0/testapp
./start.sh                 # WordPress + Kissmetrics plugin, all events enabled
./start.sh --woo           # also install WooCommerce (for Purchased-event testing)
KM_KEY=your40charkey ./start.sh   # preload your real KM tracking key
```

When it finishes it prints the URLs. Defaults:

| What          | Where                                                        |
|---------------|-------------------------------------------------------------|
| Site          | http://localhost:8080                                       |
| Admin         | http://localhost:8080/wp-admin  (**admin / admin**)         |
| KM settings   | http://localhost:8080/wp-admin/options-general.php?page=kissmetrics |

## How to test the plugin

1. **Set your key.** Open the KM settings page, paste your tracking key from
   https://app.kissmetrics.io/settings, tick the events you want, Save.
   (Or preload it: `KM_KEY=xxxx ./start.sh`.)
2. **Confirm the snippet renders.** View-source on the homepage (or `curl -s http://localhost:8080/`)
   and look for `scripts.kissmetrics.io` and your key. No key set = nothing renders (by design).
3. **Trigger events by browsing:**
   - Homepage → `Viewed Homepage`
   - Open the seeded post → `Viewed post`
   - Use the search box (`/?s=shoes`) → `Searched Site`
   - Click the in-post links → `trackClick` (internal) / `trackClickOnOutboundLink` (external)
   - Register / log in → `Created account` / `Signed in`
   - Post a comment → `Commented`
   - (with `--woo`) buy **Test Widget** and complete checkout → `Purchased` (+ identify by billing email)
4. **See events land** in your KM account's **Live View** (needs a real key + outbound internet).

## Stop / reset

```bash
./stop.sh          # stop, keep data (fast restart)
./stop.sh --wipe   # stop and delete everything (fresh site next start)
```

## Notes / gotchas
- **OPcache:** after editing the plugin PHP, run `docker compose restart wp` so the site
  picks up the new bytecode.
- **Pretty permalinks** are enabled (`/%postname%/`) — required for WooCommerce's
  order-received page (and for the `Purchased` event to fire).
- This site is **separate** from `tests/integration/` (the throwaway CI harness). This one
  persists across restarts; that one tears itself down each run.
