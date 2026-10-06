=== Site Perspectives – Your Site Structure, Mapped for Everyone ===
Contributors: apos37
Tags: sitemap, site structure, site tree, admin menu, navigation
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Build your site structure once, then share it with your team and your visitors: a drag and drop site tree, a sitemap shortcode and an admin menu tree.

== Description ==

Site Perspectives maps your site in three ways, and adds an optional helper to the Pages list.

* **Frontend:** a drag and drop tree of your pages, posts and custom post types, seeded from your primary menu. Add content and custom links, choose colors and icons for any item, and show or hide data such as path, status, ID, categories, tags and custom meta. Hide individual items from the shortcode while keeping them in the admin tree.
* **Shortcode:** place `[site_perspectives]` anywhere to show visitors a styled sitemap. Pick a theme (or match the Frontend tab), colors, layout and data, and watch a live preview update as you change settings. Drafts, private and password-protected pages are skipped automatically.
* **Backend:** the WordPress admin menu laid out as an expandable folder tree. Reorder, rename, hide items and add custom links.
* **Utility pages:** an optional setting that lets you mark pages as utility pages (login, privacy policy and similar) from Quick Edit or Bulk Actions on the Pages list. Adds Utility and Non-Utility views with counts beside All, Mine, Published, Drafts and Trash, and remembers the view you used last.

Settings include role-based access, the post types available in the Frontend tree, the utility page option and an option to delete all data on uninstall. If Admin Help Docs is active, Site Perspectives inherits its color scheme.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/site-perspectives` or install it from the Plugins screen.
2. Activate it.
3. Open Perspectives in the admin menu.

== Frequently Asked Questions ==

= Does it create an XML sitemap? =

No. Site Perspectives works alongside the XML sitemap from WordPress core or your SEO plugin and does not change it.

= How do I show the sitemap to visitors? =

Add `[site_perspectives]` to any page, post or widget that renders shortcodes. Use `[site_perspectives layout="cards"]` to override the layout for one instance.

= How do I keep a page off the shortcode but in the admin tree? =

On the Frontend tab, click Edit, open the item and check "Hide from Shortcode". Its child items are hidden too.

= How do I mark pages as utility pages? =

Turn on "Enable Utility Page Flags" in Settings. Then use Quick Edit or the "Mark as Utility" bulk action on the Pages list, and filter with the Utility and Non-Utility views. The last view you used is remembered.

== Changelog ==

= 1.0.3 =
* Renamed the plugin to Site Perspectives.
* Removed the XML sitemap and SEO tab.
* Removed the Custom CSS option from the Shortcode tab.
* Moved the admin menu item lower in the menu.
* Added the version, How-To Guide, Developer Docs and Support Forum links to the Settings page.

= 1.0.2 =
* Fixed the Backend tab freezing when entering or leaving edit mode on sites with many menu items.
* Added an "Add under" parent dropdown when adding items on the Frontend tab.
* Added a View Page display option on the Frontend tab.
* Items added together are now inserted in alphabetical order.
* Added an "Adding..." indicator to the Add to Tree buttons.
* Whitespace is trimmed when pasting into Link Text.

= 1.0.0 =
* Initial release.
* Utility page flags for the Pages list with Quick Edit, bulk actions, Utility and Non-Utility views, and a remembered last view.
* Hide from Shortcode option for Frontend tree items.
