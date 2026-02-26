=== Freeio WC Service Cart ===

Contributors: j-tap
Tags: freeio, woocommerce, cart, services
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.2
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates the Freeio theme service cart with WooCommerce so multiple services can be paid in a single order. Requires the Freeio theme and WooCommerce.

== Description ==

* Adds services to a custom cart and converts them to one WooCommerce order at checkout.
* Shortcodes: `[freeio_add_to_cart]` for the add-to-cart button, `[freeio_cart_link]` for the cart link.
* When guest checkout is disabled in WooCommerce, prompts users to log in before adding to cart (theme-style popup).
* Updates are delivered via GitHub releases when using the packaged build with vendor.

== Installation ==

1. Install and activate the Freeio theme and WooCommerce.
2. Upload the plugin to `/wp-content/plugins/` or install via Plugins → Add New.
3. Activate the plugin.
4. Create a page and add the shortcode `[freeio_service_cart]` for the cart.
5. In Freeio / Service Cart settings, select that page as the service cart page.
6. Use `[freeio_add_to_cart service_id="123"]` on service pages and `[freeio_cart_link]` in the header or menu.

== Frequently Asked Questions ==

= Do I need the Freeio theme? =

Yes. The plugin is built for the Freeio theme and will show an error if another theme is active.

= Where is the cart page? =

Create any page, add the shortcode `[freeio_service_cart]`, then set that page in the plugin settings (under the Freeio menu).

= Can guests add to cart? =

If WooCommerce guest checkout is disabled (WooCommerce → Settings → Accounts), users must log in before adding to cart. The plugin shows a theme-style message.

== Changelog ==

= 1.0.0 =
* Initial release.
* Add-to-cart via shortcode with AJAX and no-JS fallback.
* Cart page shortcode and checkout integration.
* Login required check when WooCommerce guest checkout is disabled; theme-style popup notification (right side).
* Plugin Update Checker for updates from GitHub.
