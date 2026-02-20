<?php

declare(strict_types=1);

namespace FreeioWcServiceCart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode [freeio_service_cart] — корзина услуг в стиле WooCommerce.
 */
final class Checkout_Handler {

    private const REMOVE_ACTION = 'freeio_remove_cart_item';
    private const REMOVE_NONCE = 'freeio_remove_cart_item';

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('freeio_service_cart', [$this, 'render_shortcode']);
        add_action('init', [$this, 'handle_remove_item'], 5);
    }

    public function handle_remove_item(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        if (!isset($_POST['action']) || $_POST['action'] !== self::REMOVE_ACTION) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), self::REMOVE_NONCE)) {
            wp_die(esc_html__('Ошибка безопасности.', 'freeio-wc-service-cart'), '', ['response' => 403]);
        }

        $index = isset($_POST['item_index']) ? absint($_POST['item_index']) : -1;
        $cart_instance = Service_Cart::instance();
        $cart = $cart_instance->get_cart();

        if (isset($cart[$index])) {
            array_splice($cart, $index, 1);
            $session = $this->get_session();
            if ($session !== null) {
                $session->set(SESSION_KEY, array_values($cart));
            }
        }

        wp_safe_redirect($cart_instance->get_cart_url());
        exit;
    }

    public function render_shortcode(): string {
        $cart_instance = Service_Cart::instance();
        $cart = $cart_instance->get_cart();
        $notices = $this->get_notices();
        $checkout_url = $this->get_checkout_url();

        ob_start();
        ?>
        <div class="freeio-service-cart woocommerce">
            <?php echo $notices; ?>
            <?php if (empty($cart)) : ?>
                <p class="cart-empty woocommerce-info">
                    <?php esc_html_e('Корзина услуг пуста.', 'freeio-wc-service-cart'); ?>
                </p>
            <?php else : ?>
                <div class="row">
                    <div class="col-12 col-lg-8">
                        <form method="post" action="" class="freeio-service-cart-form woocommerce-cart-form">
                            <table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th class="product-thumbnail"><?php esc_html_e('Изображение', 'freeio-wc-service-cart'); ?></th>
                                        <th class="product-name"><?php esc_html_e('Название', 'freeio-wc-service-cart'); ?></th>
                                        <th class="product-price"><?php esc_html_e('Цена', 'freeio-wc-service-cart'); ?></th>
                                        <th class="product-quantity"><?php esc_html_e('Кол-во', 'freeio-wc-service-cart'); ?></th>
                                        <th class="product-subtotal"><?php esc_html_e('Итого', 'freeio-wc-service-cart'); ?></th>
                                        <th class="product-remove">&nbsp;</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart as $index => $item) :
                                        $service_id = $item['service_id'];
                                        $title = get_the_title($service_id);
                                        $thumbnail = get_the_post_thumbnail($service_id, 'thumbnail');
                                        if ($thumbnail === '') {
                                            $thumbnail = wc_placeholder_img('thumbnail');
                                        }
                                        $price = $item['calculated_price'];
                                        $permalink = get_permalink($service_id);
                                    ?>
                                        <tr class="woocommerce-cart-form__cart-item cart_item">
                                            <td class="product-thumbnail" data-title="<?php esc_attr_e('Изображение', 'freeio-wc-service-cart'); ?>">
                                                <?php if ($permalink) : ?>
                                                    <a href="<?php echo esc_url($permalink); ?>"><?php echo $thumbnail; ?></a>
                                                <?php else : ?>
                                                    <?php echo $thumbnail; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-name" data-title="<?php esc_attr_e('Название', 'freeio-wc-service-cart'); ?>">
                                                <?php if ($permalink) : ?>
                                                    <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
                                                <?php else : ?>
                                                    <?php echo esc_html($title); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="product-price" data-title="<?php esc_attr_e('Цена', 'freeio-wc-service-cart'); ?>">
                                                <?php echo wc_price($price); ?>
                                            </td>
                                            <td class="product-quantity" data-title="<?php esc_attr_e('Кол-во', 'freeio-wc-service-cart'); ?>">
                                                1
                                            </td>
                                            <td class="product-subtotal" data-title="<?php esc_attr_e('Итого', 'freeio-wc-service-cart'); ?>">
                                                <?php echo wc_price($price); ?>
                                            </td>
                                            <td class="product-remove">
                                                <button type="submit"
                                                    name="action"
                                                    value="<?php echo esc_attr(self::REMOVE_ACTION); ?>"
                                                    class="remove"
                                                    aria-label="<?php esc_attr_e('Удалить', 'freeio-wc-service-cart'); ?>"
                                                    title="<?php esc_attr_e('Удалить', 'freeio-wc-service-cart'); ?>"
                                                    formaction="<?php echo esc_url($cart_instance->get_cart_url()); ?>">
                                                    <i class="flaticon-delete"></i>
                                                </button>
                                                <input type="hidden" name="item_index" value="<?php echo esc_attr((string) $index); ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php wp_nonce_field(self::REMOVE_NONCE, '_wpnonce'); ?>
                        </form>
                    </div>
                    <div class="col-12 col-lg-4">
                        <div class="cart-collaterals">
                            <div class="cart_totals">
                                <h2><?php esc_html_e('Итоги корзины', 'freeio-wc-service-cart'); ?></h2>
                                <table class="shop_table shop_table_responsive" cellspacing="0">
                                    <tbody>
                                        <tr class="cart-subtotal">
                                            <th><?php esc_html_e('Подитог', 'freeio-wc-service-cart'); ?></th>
                                            <td><?php echo wc_price($cart_instance->get_total()); ?></td>
                                        </tr>
                                        <tr class="order-total">
                                            <th><?php esc_html_e('Итого', 'freeio-wc-service-cart'); ?></th>
                                            <td><strong><?php echo wc_price($cart_instance->get_total()); ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div class="wc-proceed-to-checkout">
                                    <a href="<?php echo esc_url($checkout_url); ?>" class="checkout-button button alt wc-forward">
                                        <?php esc_html_e('Перейти к оплате', 'freeio-wc-service-cart'); ?><i class="flaticon-right-up next"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function get_checkout_url(): string {
        $url = apply_filters('freeio_wc_service_cart_checkout_url', null);
        if (is_string($url) && $url !== '') {
            return $url;
        }
        if (function_exists('wc_get_checkout_url')) {
            return wc_get_checkout_url();
        }
        return home_url('/');
    }

    /**
     * @return \WC_Session|null
     */
    private function get_session(): ?object {
        if (!function_exists('WC') || !WC()->session) {
            return null;
        }
        return WC()->session;
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

        $class = $type === 'success' ? 'woocommerce-message' : 'woocommerce-error';
        return '<div class="woocommerce-notices-wrapper"><div class="' . esc_attr($class) . '">' . esc_html($message) . '</div></div>';
    }
}
