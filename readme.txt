=== Aide :: Demo Data Import ===
Contributors: aide247
Tags: demo, import, woocommerce, sample content, dummy data
Requires at least: 6.0
Tested up to: 7.1.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import curated demo posts, media, and optional WooCommerce products, customers, and orders from Tools in wp-admin.

== Description ==

**Aide :: Demo Data Import** helps you populate a WordPress site with sample content for demos, theme previews, and development.

**Features**

* Import blog/news posts with categories, tags, and featured images
* Import sample media from the Aide CDN (https://wp.aide247.com/)
* When WooCommerce is active: import products, customers, and orders
* Generate additional demo records dynamically for large catalogs (up to 5,000 per type)
* Batched AJAX import with progress feedback
* Remove only demo-tagged content without touching your real data

WooCommerce is **optional**. The plugin works without it; WooCommerce import cards appear when WooCommerce is installed and active.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate **Aide :: Demo Data Import** on the Plugins screen
3. Open **Tools → Demo Data Import**
4. Choose a content type and click **Import**

Recommended order: Media → Posts → Products → Customers → Orders.

== Frequently Asked Questions ==

= Does this require WooCommerce? =

No. Posts and media work on any WordPress site. Product, customer, and order imports require WooCommerce.

= Will importing overwrite my content? =

No. Demo items use unique demo IDs. Re-running an import skips items that were already imported by this plugin.

= How do I remove demo content? =

Use **Remove Demo Data** on each content card, or delete the plugin (uninstall removes tagged demo content by default).

= Can I import thousands of records? =

Yes. The first 100 per type are curated JSON. Higher counts are generated dynamically and reuse CDN images in a cycle. Default maximum is 5,000 per type.

= Do I need the image files in the plugin folder? =

No. Images are downloaded from `https://wp.aide247.com/aidedemodataimport/demo-images/` during import. The site must allow outbound HTTPS requests.

== Changelog ==

= 1.0.0 =
* Initial release: posts, media, and WooCommerce product/customer/order imports
* Remote CDN images and dynamic generation for large import counts

== Upgrade Notice ==

= 1.0.0 =
Initial release.
