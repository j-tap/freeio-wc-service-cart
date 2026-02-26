=== Freeio WC Service Cart ===

Contributors: j-tap
Tags: freeio, woocommerce, cart, services
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.1
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates the Freeio theme service cart with WooCommerce so multiple services can be paid in a single order. Requires the Freeio theme and WooCommerce.

== Description ==

* Adds services to a custom cart and converts them to one WooCommerce order at checkout.
* Shortcodes: `[freeio_add_to_cart]` for the add-to-cart button, `[freeio_cart_link]` for the cart link.
* When guest checkout is disabled in WooCommerce, prompts users to log in before adding to cart (theme-style popup).
* Updates are delivered via GitHub releases when using the packaged build with vendor.

== Changelog ==

= 1.0.0 =
* Initial release.
* Add-to-cart via shortcode with AJAX and no-JS fallback.
* Cart page shortcode and checkout integration.
* Login required check when WooCommerce guest checkout is disabled; theme-style popup notification (right side).
* Plugin Update Checker for updates from GitHub.
