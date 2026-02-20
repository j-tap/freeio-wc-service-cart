<?php

declare(strict_types=1);

namespace FreeioWcServiceCart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode [freeio_service_cart] и создание WooCommerce order при переходе к оплате.
 */
final class Checkout_Handler {

    private const PROCEED_ACTION = 'freeio_proceed_to_payment';
    private const PROCEED_NONCE = 'freeio_proceed_to_payment';

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('freeio_service_cart', [$this, 'render_shortcode']);
        add_action('init', [$this, 'handle_proceed_to_payment'], 5);
    }

    public function handle_proceed_to_payment(): void {
        if (
            !isset($_POST['action']) || $_POST['action'] !== self::PROCEED_ACTION
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
        ) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), self::PROCEED_NONCE)) {
            wp_die(esc_html__('Ошибка безопасности.', 'freeio-wc-service-cart'), '', ['response' => 403]);
        }

        $cart = Service_Cart::instance()->get_cart();
        if (empty($cart)) {
            wp_safe_redirect(Service_Cart::instance()->get_cart_url());
            exit;
        }

        $order = $this->create_wc_order($cart);
        if ($order === null) {
            wp_safe_redirect(Service_Cart::instance()->get_cart_url());
            exit;
        }

        $checkout_url = $order->get_checkout_payment_url();
        wp_safe_redirect($checkout_url);
        exit;
    }

    /**
     * @param list<array{service_id: int, package_key: string|null, addons: array<int>, calculated_price: float}> $cart
     * @return \WC_Order|null
     */
    private function create_wc_order(array $cart): ?object {
        if (!function_exists('wc_create_order')) {
            return null;
        }

        $total = 0.0;
        foreach ($cart as $item) {
            $total += $item['calculated_price'];
        }

        $order = wc_create_order();
        if (!$order instanceof \WC_Order) {
            return null;
        }

        $fee = new \WC_Order_Item_Fee();
        $fee->set_name(__('Freeio Services Order', 'freeio-wc-service-cart'));
        $fee->set_amount($total);
        $fee->set_total($total);
        $order->add_item($fee);
        $order->update_meta_data(META_SERVICE_CART, $cart);
        $order->save();
        $order->calculate_totals();

        return $order;
    }

    public function render_shortcode(): string {
        $cart = Service_Cart::instance()->get_cart();
        $notices = $this->get_notices();

        ob_start();
        ?>
        <div class="freeio-service-cart">
            <?php echo $notices; ?>
            <?php if (empty($cart)) : ?>
                <p><?php esc_html_e('Корзина услуг пуста.', 'freeio-wc-service-cart'); ?></p>
            <?php else : ?>
                <ul class="freeio-service-cart-list">
                    <?php foreach ($cart as $item) : ?>
                        <li class="freeio-service-cart-item">
                            <?php
                            printf(
                                /* translators: 1: service id */
                                esc_html__('Услуга #%1$d', 'freeio-wc-service-cart') . ' — %2$s',
                                $item['service_id'],
                                wc_price($item['calculated_price'])
                            );
                            ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="freeio-service-cart-total">
                    <strong><?php esc_html_e('Итого:', 'freeio-wc-service-cart'); ?></strong>
                    <?php echo wc_price(Service_Cart::instance()->get_total()); ?>
                </p>
                <form method="post" action="">
                    <?php wp_nonce_field(self::PROCEED_NONCE, '_wpnonce'); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::PROCEED_ACTION); ?>">
                    <button type="submit" name="freeio_proceed" class="freeio-service-cart-proceed">
                        <?php esc_html_e('Proceed to Payment', 'freeio-wc-service-cart'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function get_notices(): string {
        $type = isset($_GET['freeio_cart_notice']) ? sanitize_text_field(wp_unslash($_GET['freeio_cart_notice'])) : '';
        $code = isset($_GET['freeio_cart_code']) ? sanitize_text_field(wp_unslash($_GET['freeio_cart_code'])) : '';
        if ($type === '' || $code === '') {
            return '';
        }

        $messages = [
            'success' => [
                'added' => __('Услуга добавлена в корзину.', 'freeio-wc-service-cart'),
            ],
            'error' => [
                'invalid_service' => __('Неверный идентификатор услуги.', 'freeio-wc-service-cart'),
                'price_error' => __('Не удалось рассчитать цену услуги.', 'freeio-wc-service-cart'),
                'security' => __('Ошибка безопасности. Обновите страницу и попробуйте снова.', 'freeio-wc-service-cart'),
            ],
        ];

        $message = $messages[$type][$code] ?? '';
        if ($message === '') {
            return '';
        }

        $class = $type === 'success' ? 'notice-success' : 'notice-error';
        return '<div class="freeio-cart-notice notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }
}
