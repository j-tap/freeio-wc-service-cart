# Freeio WC Service Cart

Plugin that integrates the Freeio service cart with WooCommerce: multiple services, single payment. WooCommerce is used only as the payment layer; Freeio orders are created after payment.

## Requirements

- WordPress 6+
- WooCommerce 8+
- PHP 8.1+
- **Freeio** theme (required; the plugin will not activate without it)

## Installation

1. Copy the `freeio-wc-service-cart` folder to `wp-content/plugins/`.
2. Activate the plugin in the WordPress admin.
3. Create a page with the shortcode `[freeio_service_cart]`.
4. Set that page in settings: **Freeio Settings → Service Cart** — select the cart page from the dropdown and save.

   The Freeio parent menu in the theme uses the slug `freelancer-settings`. Override it: `add_filter('freeio_wc_service_cart_settings_parent_slug', fn() => 'other-slug');`

   Alternative (via code): option `freeio_wc_service_cart_page_id`, or use the URL filter:

```php
add_filter('freeio_wc_service_cart_page_url', fn() => 'https://yoursite.com/service-cart/');
```

## Adding a service to the cart

### Button shortcode

On the service page (or in a widget) insert:

```
[freeio_add_to_cart service_id="123"]
```

Parameters (all except `service_id` are optional):

| Parameter   | Description |
|------------|-------------|
| `service_id` | Service ID (required) |
| `package`    | Package key (service_package) |
| `addons`     | Comma-separated addon IDs, e.g. `addons="1,2,3"` |
| `text`       | Button label (default: "Add to cart") |
| `class`      | CSS classes for the button (default: `freeio-add-to-cart-btn button`) |

Examples:

```
[freeio_add_to_cart service_id="15"]
[freeio_add_to_cart service_id="15" package="premium" text="Order now"]
[freeio_add_to_cart service_id="15" addons="1,2" class="btn btn-primary"]
```

The form is submitted to the **current page** (the service page). POST is handled on the earliest `init` hook (priority 0), before theme output, and redirects to the cart page. The `/freeio-add-to-cart/` URL is not used when clicking the button; an empty page at that URL does not affect the button.

### Cart link in the header

Shortcode **`[freeio_cart_link]`** — link to the cart page with icon and item count (for header/menu).

Parameters (all optional):

| Parameter   | Description | Default |
|------------|-------------|---------|
| `class`    | CSS class for the link | `freeio-cart-link` |
| `icon`     | Show cart icon (`yes`/`no`) | `yes` |
| `show_count` | Show item count | `yes` |
| `icon_class` | Icon class (Freeio theme uses Flaticon, e.g. `flaticon-shopping-cart`) | `flaticon-shopping-cart` |

Examples:

```
[freeio_cart_link]
[freeio_cart_link class="header-cart" icon_class="flaticon-shopping-cart"]
[freeio_cart_link icon_class="flaticon-cart" show_count="no"]
```

The icon is rendered as inline SVG. If your theme uses a different cart icon class, set it via `icon_class`.

### Custom form (POST)

POST form to any URL with fields:

- `action=freeio_add_service_to_cart`
- `_wpnonce` (wp_nonce_field for action `freeio_add_service_to_cart`)
- `service_id` (required)
- `service_package` (optional)
- `service_addons[]` (optional, array of addon IDs)

## Freeio integration

### Price calculation

The plugin tries to get the price via post meta keys (`_price`, `_service_price`, `_regular_price`, `price`). Override with the filter:

```php
add_filter('freeio_wc_service_cart_calculate_price', function ($price, $service_id, $package_key, $addons) {
    // return (float) price or null on error
    return 99.00;
}, 10, 4);
```

### Creating Freeio orders after payment

After successful WooCommerce payment the plugin fires an action for each cart item:

```php
add_action('freeio_wc_service_cart_create_freeio_order', function ($cart_item, $wc_order_id, $wc_order) {
    // $cart_item: service_id, package_key, addons, calculated_price
    // Create Freeio order and save in its meta: wc_order_id = $wc_order_id
}, 10, 3);
```

Also available:

- `freeio_wc_service_cart_before_orders_sync` — before creating orders (receives $order, $cart).
- `freeio_wc_service_cart_after_orders_sync` — after creating orders and clearing the cart.

## Cart item structure

- `service_id` (int)
- `package_key` (string|null)
- `addons` (array<int>)
- `calculated_price` (float)

Duplicate items are not merged in the cart.

## Translations

The plugin uses the text domain `freeio-wc-service-cart`. Supported locales:

- **ru_RU** — Russian
- **en_US** — English
- **es_ES** — Spanish

Translation files: `languages/freeio-wc-service-cart-{locale}.po` and compiled `.mo`. After editing `.po`, rebuild `.mo`:

```bash
msgfmt -o languages/freeio-wc-service-cart-ru_RU.mo languages/freeio-wc-service-cart-ru_RU.po
```
