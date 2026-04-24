=== WP Numeric Slug Fixer ===
Contributors: mypacecreator
Tags: slug, permalink, numeric, redirect, url
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fixes numeric-only post slugs that get misrouted to date archives under the Post name permalink structure.

== Description ==

When WordPress is configured to use the **Post name** permalink structure (`/%postname%/`), a post whose slug consists entirely of digits — for example `2023` or `42` — is never served directly. WordPress's built-in rewrite rules match any numeric path segment against year, month, and day archive patterns first, so a request for `/2023/` is silently redirected to the year-2023 archive instead of the post.

WP Numeric Slug Fixer solves this by intercepting the save operation *before* the slug is written to the database. If the slug is purely numeric, the plugin prepends a short prefix (`post-` by default), turning `2023` into `post-2023` and eliminating the routing conflict entirely.

**Key characteristics**

* Zero configuration — works out of the box.
* No settings page or stored options; nothing to clean up on uninstall. Includes a one-time Tools page for fixing existing numeric slugs.
* Only activates when the `%postname%` permalink structure is in use; other structures are left untouched.
* Applies to all public post types (posts, pages, and custom post types).
* The prefix is customizable via the `wpnsf_prefix` filter hook for developers. Post types that should never have their numeric slugs modified (for example, custom post types used internally) can be excluded via the `wpnsf_excluded_post_types` filter.

**Fixing posts saved before the plugin was installed**

If posts were already published with numeric-only slugs before you activated the plugin, go to **Tools > Fix Numeric Slugs** in the WordPress admin. The page lists every affected post and offers a **Fix All** button to prefix all of them in a single step — no manual re-saving required. The operation is idempotent: once all slugs are prefixed the page shows nothing left to fix, and clicking Fix All again has no effect. Administrator access (the `manage_options` capability) is required to use this page.

**Customising the prefix**

Add the following snippet to your theme's `functions.php` or a site-specific plugin:

`add_filter( 'wpnsf_prefix', function() { return 'article-'; } );`

After changing the prefix, any previously saved numeric slugs must be re-saved to pick up the new value, since the filter only runs during a save operation.

== Installation ==

= From the WordPress admin =

1. Go to **Plugins > Add New Plugin**.
2. Search for **WP Numeric Slug Fixer**.
3. Click **Install Now**, then **Activate**.

= Manual upload =

1. Download the plugin ZIP file.
2. Go to **Plugins > Add New Plugin > Upload Plugin**.
3. Select the ZIP file and click **Install Now**, then **Activate**.

= Via FTP =

1. Upload the `wp-numeric-slug-fixer` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the **Plugins** screen in the WordPress admin.

== Frequently Asked Questions ==

= Why does WordPress misroute numeric slugs in the first place? =

WordPress registers rewrite rules that map numeric URL segments to date-based archive queries (year, month, day). These rules take priority over the `%postname%` rule, so a URL like `/2023/` matches the year-archive rule and triggers a redirect before WordPress ever tries to look up a post with that slug.

= Will this affect existing posts that already have numeric slugs? =

Not automatically. The filter only runs when a post is saved. If a post was created with a numeric slug before the plugin was activated, it will keep that slug until it is re-saved. To fix all such posts at once, go to **Tools > Fix Numeric Slugs** and click **Fix All**.

= Can I use a prefix other than "post-"? =

Yes. Use the `wpnsf_prefix` filter:

`add_filter( 'wpnsf_prefix', function() { return 'p-'; } );`

= Does this plugin affect pages or custom post types? =

Yes — the plugin applies to all post types that use the standard WordPress slug mechanism, as long as the active permalink structure includes `%postname%`.

= Does this plugin add any database tables or options? =

No. There is no stored configuration and nothing to clean up after uninstallation.

== Changelog ==

= 1.2.0 =
* New: `wpnsf_excluded_post_types` filter to exclude specific post types from numeric slug fixing (both on save and in the bulk-fix Tools page).
* Fix: Navigation menu items (`nav_menu_item`) are now excluded from the save-time filter; they always carry numeric slugs by design and should not be modified.

= 1.1.0 =
* New: Tools > Fix Numeric Slugs page to bulk-fix posts that were saved with numeric-only slugs before the plugin was activated.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
No database changes. Navigation menu items are now correctly excluded from slug fixing. Use the new `wpnsf_excluded_post_types` filter to exclude additional post types if needed.

= 1.1.0 =
No database changes. Visit Tools > Fix Numeric Slugs after upgrading to retroactively fix posts saved before the plugin was installed.

= 1.0.0 =
Initial release — no upgrade steps required.
