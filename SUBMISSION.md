# Publishing Kissmetrics to the WordPress.org plugin directory

Everything that can be prepped in code is done. This file is the human runbook for the
rest. The goal: the plugin appears in the WordPress.org directory so any WordPress user can
install it from their dashboard.

## Status

**Ready (done):**
- [x] Plugin code hardened, `php -l` clean, unit smoke 18/18, Plugin Check 0 errors.
- [x] `readme.txt` in WP.org format with **External services** disclosure + **Screenshots** section.
- [x] GPLv3 license + `license.txt`, `uninstall.php`, ABSPATH guards, i18n text domain `kissmetrics`.
- [x] Header URIs verified live (Plugin URI, Author URI, settings URL, Terms, Privacy).
- [x] Submission ZIP built: **`kissmetrics-0.3.0.zip`** (single top-level `kissmetrics/` folder).
- [x] User-verified end-to-end: real browser purchase → events landed in live KM account.

**Pending — needs a human:**
- [ ] **wordpress.org account** — use the `@kissmetrics.io` one (trademark: proves it's official).
- [ ] **Directory images** — see `.wordpress-org/assets/SPECS.md` (icon, banner, screenshots).
- [ ] **Submit + respond to reviewer** (steps below).
- [ ] **Verify `Contributors:` field** in `readme.txt` lists real wordpress.org usernames
      (currently `kissmetrics` — must be an actual registered wp.org username, or change it).

## Steps

### 1. Submit for review (one-time)
1. Log in at https://wordpress.org/ with the `@kissmetrics.io` account.
2. Go to https://wordpress.org/plugins/developers/add/.
3. Upload **`kissmetrics-0.3.0.zip`**.
4. Submit. A human from the Plugin Review Team reviews it (typically a few days to ~2 weeks).

### 2. Handle the review reply
- They email the submitting account. They may request changes (common for tracking plugins).
- The **External services** disclosure and trademark ownership are the two most likely topics —
  both are already addressed (disclosure is in the readme; submit from `@kissmetrics.io`).
- Make any requested edits, reply. Repeat until approved. On approval they create the SVN repo.

### 3. Publish via SVN (after approval)
You'll get `https://plugins.svn.wordpress.org/kissmetrics/`. Then:
```bash
svn checkout https://plugins.svn.wordpress.org/kissmetrics/ km-svn
cd km-svn
# plugin files -> trunk/
cp -r <path>/kissmetrics_0.3.0/kissmetrics/* trunk/
# tag the release
svn cp trunk tags/0.3.0
# directory images -> assets/  (NOT trunk)
cp <path>/kissmetrics_0.3.0/.wordpress-org/assets/*.png assets/
svn add --force trunk tags assets
svn ci -m "Kissmetrics 0.3.0"
```
The `Stable tag: 0.3.0` line in `readme.txt` tells WP.org which tag to serve. It goes live
within minutes. Users can now install "Kissmetrics" from their WP dashboard.

### 4. Future releases
Bump `Version:` (in `kissmetrics.php`) and `Stable tag:` (in `readme.txt`), copy to a new
`tags/x.y.z/`, `svn ci`.

## Notes
- WordPress.org is **SVN**, separate from our GitHub. Keep developing in git (recommended home:
  the `km-wordpress` repo); SVN is only the publish target.
- The ZIP intentionally contains **only** `kissmetrics/` — tests, testapp, and this runbook are
  dev-only and must not ship in the plugin.
