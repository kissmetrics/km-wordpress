=== Kissmetrics ===
Contributors: kissmetrics
Tags: analytics, kissmetrics, tracking, behavior, woocommerce
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Install Kissmetrics and automatically track pageviews, sign-ups, search, comments, social interactions, and WooCommerce purchases — no code required.

== Description ==

The official Kissmetrics plugin makes integrating Kissmetrics into your WordPress blog, website, or store effortless. Paste your API key once and the plugin installs your tracking code and captures a rich set of behavioral events with minimal configuration.

Easily track:

* Homepage, post, and page views (with title, categories, and tags)
* Sign in and registration events
* Link clicks in posts, pages, and comments
* Search queries
* Comment submissions
* Social interactions (Facebook, Twitter)
* WooCommerce purchases — revenue, items, currency, and order ID

Automatically identify:

* Authenticated users by username
* WooCommerce buyers by billing email
* Unregistered commenters by email address

All tracking is opt-in per event type from a single settings screen.

== Installation ==

1. Upload the `kissmetrics` folder to `/wp-content/plugins/`, or install it from the Plugins screen in your WordPress dashboard.
2. Go to the `Plugins` menu and activate **Kissmetrics**.
3. Open `Settings → Kissmetrics`.
4. Paste your API key (from https://app.kissmetrics.io/settings) and choose which events to track.

== Frequently Asked Questions ==

= Where do I find my API key? =

In your Kissmetrics account under Settings: https://app.kissmetrics.io/settings

= Does it work with WooCommerce? =

Yes. Enable **Track purchases** under the WooCommerce section of the settings screen. The plugin records a `Purchased` event on the order-received page and identifies the buyer by billing email.

= Does it work with block (FSE) themes? =

Yes. Page context (homepage, search, etc.) is detected server-side with WordPress conditional tags rather than theme CSS classes, so events fire correctly on modern block themes.

= Does it slow down my site? =

No. The Kissmetrics library loads asynchronously and is only output once an API key is configured.

== External services ==

This plugin connects to Kissmetrics, a third-party analytics service, to record how visitors interact with your site. This is the core purpose of the plugin and it is only active once you enter your Kissmetrics API key.

What it does and the data it sends:

* **Loads the Kissmetrics JavaScript library** from `https://i.kissmetrics.io/i.js` and `https://scripts.kissmetrics.io/<your-api-key>.2.js` on your site's front end. These scripts run in the visitor's browser and transmit event data to Kissmetrics.
* **Sends behavioral events** to Kissmetrics as visitors browse — for example page and post views, searches, link clicks, sign-ups, log-ins, comments, and (if WooCommerce tracking is enabled) completed purchases including order total, currency, item names, and order ID.
* **Sends identifying data** when available — the username of a logged-in user, the billing email of a WooCommerce buyer, or the email address a commenter enters — so that activity can be attributed to a person in Kissmetrics.

Data is sent only for the event types you enable on the plugin's settings screen, and only after you configure an API key. No data is sent to Kissmetrics before then.

Kissmetrics is provided by KISSmetrics. By using this plugin you send visitor data to Kissmetrics, so please review their policies and ensure your own site's privacy notice reflects this:

* Terms of Use: https://www.kissmetrics.io/terms
* Privacy Policy: https://www.kissmetrics.io/privacy

== Screenshots ==

1. The Kissmetrics settings screen — paste your API key and choose which events to track.
2. The WooCommerce section of the settings screen — opt in to purchase tracking.
3. Tracked events appearing in the Kissmetrics Live View.

== Changelog ==

= 0.3.0 =
* New: WooCommerce purchase tracking (revenue, items, currency, order ID; identifies buyer by billing email).
* Fixed: outbound-link detection (origin domain was unresolved, classifying every link as outbound).
* Fixed: replaced deprecated `get_currentuserinfo()` with `wp_get_current_user()`.
* Fixed: PHP 8 "undefined array key" notice when reading the comment-author cookie.
* Fixed: homepage and search detection now use server-side conditional tags, so they work on block/FSE themes.
* Security: escape all dynamic values (`esc_js`/`wp_json_encode`) before output; unslash and sanitize all superglobals.
* Compliance: output scripts through the queue (`wp_add_inline_script`), added text domain/i18n, `uninstall.php`, and direct-access guards.

= 0.2.0 =
* Updated plugin to work with PHP 8.0.

= 0.1.1 =
* Improved outbound link detection.

= 0.1.0 =
* Update to kissmetrics.io domain.

= 0.0.3 =
* Bugfix: correct use of check_admin_referer with nonce.

= 0.0.2 =
* Bugfix: closing JS script tag would not appear unless you opted to track Search Queries.

= 0.0.1 =
* Initial version.

== Upgrade Notice ==

= 0.3.0 =
Adds WooCommerce purchase tracking, fixes outbound-link and PHP 8 / block-theme issues, and hardens the plugin for the WordPress.org directory.
