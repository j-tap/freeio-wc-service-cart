<?php

declare(strict_types=1);

/**
 * Plugin Name: Freeio WC Service Cart
 * Description: Integrates Freeio service cart with WooCommerce (single payment for multiple services).
 * Version: 0.1.3
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * Author: j-tap
 * License: GPL v2 or later
 * Text Domain: freeio-wc-service-cart
 */

namespace FreeioWcServiceCart;

if (!defined('ABSPATH')) {
    exit;
}

define(
    __NAMESPACE__ . '\PLUGIN_VERSION',
    (static function (): string {
        $readme = __DIR__ . '/readme.txt';
        if (is_file($readme) && ($content = file_get_contents($readme)) !== false) {
            if (preg_match('/^Stable tag:\s*(.+)$/mi', $content, $m)) {
                return trim($m[1]);
            }
        }
        return '0.0.0';
    })()
);
const META_SERVICE_CART = 'freeio_service_cart';
const SESSION_KEY = 'freeio_service_cart';
const ADD_TO_CART_ACTION = 'freeio_add_service_to_cart';
const NONCE_ACTION = 'freeio_add_service_to_cart';
const PROXY_PRODUCT_OPTION = 'freeio_wc_service_proxy_product_id';
const PROXY_PRODUCT_SKU = '_freeio-service-proxy';
const CART_ITEM_META_KEY = '_freeio_service';

final class Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init'], 20);
    }

    public function init(): void {
        $this->load_textdomain();
        if (!$this->dependencies_met()) {
            return;
        }

        $this->load_classes();
        $this->boot();
    }

    private function load_textdomain(): void {
        load_plugin_textdomain(
            'freeio-wc-service-cart',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    private function dependencies_met(): bool {
        if (get_template() !== 'freeio') {
            add_action('admin_notices', function (): void {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Freeio WC Service Cart требует активную тему Freeio.', 'freeio-wc-service-cart')
                    . '</p></div>';
            });
            return false;
        }
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function (): void {
                echo '<div class="notice notice-error"><p>'
                    . esc_html__('Freeio WC Service Cart требует активный WooCommerce.', 'freeio-wc-service-cart')
                    . '</p></div>';
            });
            return false;
        }
        return true;
    }

    private function load_classes(): void {
        require_once __DIR__ . '/includes/class-service-cart.php';
        require_once __DIR__ . '/includes/class-checkout-handler.php';
        require_once __DIR__ . '/includes/class-order-sync.php';
        if (is_admin()) {
            require_once __DIR__ . '/includes/class-admin-settings.php';
        }
    }

    private function boot(): void {
        Service_Cart::instance();
        Checkout_Handler::instance();
        Order_Sync::instance();
        if (is_admin()) {
            Admin_Settings::instance();
        }
    }

    public static function plugin_path(): string {
        return plugin_dir_path(__FILE__);
    }

    public static function plugin_url(): string {
        return plugin_dir_url(__FILE__);
    }
}

function ensure_proxy_product(): int {
    $existing_id = (int) get_option(PROXY_PRODUCT_OPTION, 0);
    if ($existing_id > 0 && get_post_status($existing_id) === 'publish') {
        return $existing_id;
    }

    $product = new \WC_Product_Simple();
    $product->set_name('Freeio Service');
    $product->set_status('publish');
    $product->set_catalog_visibility('hidden');
    $product->set_sku(PROXY_PRODUCT_SKU);
    $product->set_price(0);
    $product->set_regular_price(0);
    $product->set_virtual(true);
    $product->set_sold_individually(false);
    $product->save();

    $id = $product->get_id();
    update_option(PROXY_PRODUCT_OPTION, $id);
    return $id;
}

register_activation_hook(__FILE__, function (): void {
    require_once __DIR__ . '/includes/class-service-cart.php';
    Service_Cart::register_rewrite_rule();
    flush_rewrite_rules();

    if (class_exists('WooCommerce')) {
        ensure_proxy_product();
    }
});

register_deactivation_hook(__FILE__, function (): void {
    flush_rewrite_rules();
    $proxy_id = (int) get_option(PROXY_PRODUCT_OPTION, 0);
    if ($proxy_id > 0) {
        wp_delete_post($proxy_id, true);
        delete_option(PROXY_PRODUCT_OPTION);
    }
});

Plugin::instance();
