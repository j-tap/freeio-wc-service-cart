<?php

declare(strict_types=1);

namespace FreeioWcServiceCart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * После успешной оплаты WC order: синхронизация с Freeio (создание заказов Freeio, связь с WC order).
 */
final class Order_Sync {

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('woocommerce_payment_complete', [$this, 'sync_after_payment'], 10, 3);
        add_action('woocommerce_order_status_processing', [$this, 'sync_on_processing'], 10, 2);
    }

    /**
     * @param int $order_id
     * @param mixed ...$args
     */
    public function sync_after_payment(int $order_id, ...$args): void {
        $this->sync_order($order_id);
    }

    /**
     * @param int $order_id
     * @param \WC_Order|null $order
     */
    public function sync_on_processing(int $order_id, $order = null): void {
        $this->sync_order($order_id);
    }

    private function sync_order(int $wc_order_id): void {
        $order = wc_get_order($wc_order_id);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $cart = $order->get_meta(META_SERVICE_CART);
        if (!is_array($cart) || empty($cart)) {
            return;
        }

        if ($order->get_meta('_freeio_wc_service_cart_synced') === 'yes') {
            return;
        }

        do_action('freeio_wc_service_cart_before_orders_sync', $order, $cart);

        foreach ($cart as $item) {
            if (!is_array($item) || empty($item['service_id'])) {
                continue;
            }
            do_action('freeio_wc_service_cart_create_freeio_order', $item, $wc_order_id, $order);
        }

        $order->update_meta_data('_freeio_wc_service_cart_synced', 'yes');
        $order->save();

        do_action('freeio_wc_service_cart_after_orders_sync', $order, $cart);

        Service_Cart::instance()->clear();
    }
}
