Kissmetrics
===========

Contributors: endtwist, eskfung, nathanielwroblewski, bjsummerfield
Tags: analytics
Requires at least: 3.0
Tested up to: 3.3.1
Stable tag: 0.1.1

Using Kissmetrics, automagically track post views, authentication events, social interactions, and much more.

Description
-----------

Make integrating Kissmetrics into your Wordpress blog or website a breeze. Automatically track a variety of view,
authentication, and social interaction events with minimal configuration.

Easily track:
- Homepage and individual post and page views
- Sign in and registration events
- Link clicks in posts, pages and comments
- Facebook likes, unlikes, and authentication
- Search queries
- Comment submissions

Automatically identify:
- Authenticated users by username
- Unregistered commenters by email address

Installation
------------

1. Upload the entire `kissmetrics` folder to the `/wp-content/plugins/` directory.
2. In the WordPress admin dashboard, go to the `Plugins` menu and activate the plugin.
3. Go to `Settings` menu, then to the Kissmetrics settings.
4. Add your API key here and select which events you would like to have automatically tracked.

Publishing a new version
------------------------

After bumping the version, be sure to update the latest version of the plug-in that Kissmetrics hosts in S3 at https://s3.amazonaws.com/kissmetrics-support-files/assets/integrations/wordpress/kissmetrics.zip

```sh
$ zip -r ./kissmetrics.zip ./kissmetrics
$ aws --profile km s3 cp ./kissmetrics.zip s3://kissmetrics-support-files/assets/integrations/wordpress/kissmetrics.zip
$ rm ./kissmetrics.zip
```

Changelog
---------

- 0.1.1 Improved outbound link detection
- 0.1.0 Update to kissmetrics.io domain
- 0.0.3 Bugfix: correct use of check_admin_referer with nonce
- 0.0.2 Bugfix: closing JS script tag would not appear unless you opted to track Search Queries
- 0.0.1 Initial version
