<?php

declare(strict_types=1);

namespace FreeioWcServiceCart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode [freeio_service_cart] — service cart page.
 * Transfers cart to WC Cart and redirects to standard WC Checkout.
 */
final class Checkout_Handler {

    private const REMOVE_ACTION = 'freeio_remove_cart_item';
    private const REMOVE_NONCE = 'freeio_remove_cart_item';
    private const CHECKOUT_ACTION = 'freeio_proceed_checkout';
    private const CHECKOUT_NONCE = 'freeio_proceed_checkout';

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
        add_action('init', [$this, 'handle_proceed_to_checkout'], 5);
        add_action('woocommerce_before_calculate_totals', [$this, 'override_cart_item_price'], 20);
        add_filter('woocommerce_cart_item_name', [$this, 'override_cart_item_name'], 20, 3);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'override_cart_item_thumbnail'], 20, 3);
        add_filter('woocommerce_cart_item_permalink', [$this, 'override_cart_item_permalink'], 20, 3);
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

    public function handle_proceed_to_checkout(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        if (!isset($_POST['action']) || $_POST['action'] !== self::CHECKOUT_ACTION) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce_checkout'] ?? '')), self::CHECKOUT_NONCE)) {
            wp_die(esc_html__('Ошибка безопасности.', 'freeio-wc-service-cart'), '', ['response' => 403]);
        }

        $cart_instance = Service_Cart::instance();
        $cart = $cart_instance->get_cart();

        if (empty($cart)) {
            wp_safe_redirect($cart_instance->get_cart_url());
            exit;
        }

        $ok = $this->transfer_to_wc_cart($cart);
        if (!$ok) {
            wp_safe_redirect(
                add_query_arg(
                    ['freeio_cart_notice' => 'error', 'freeio_cart_code' => 'transfer_error'],
                    $cart_instance->get_cart_url()
                )
            );
            exit;
        }

        $cart_instance->clear();

        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    /**
     * Transfer service cart items to WC Cart as proxy product with meta.
     * @param list<array{service_id: int, package_key: string|null, addons: array<int>, calculated_price: float}> $cart
     */
    private function transfer_to_wc_cart(array $cart): bool {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }

        $proxy_id = (int) get_option(PROXY_PRODUCT_OPTION, 0);
        if ($proxy_id <= 0 || get_post_status($proxy_id) !== 'publish') {
            $proxy_id = ensure_proxy_product();
        }
        if ($proxy_id <= 0) {
            return false;
        }

        WC()->cart->empty_cart();

        foreach ($cart as $item) {
            $service_id = $item['service_id'];
            $price = (float) $item['calculated_price'];
            $title = get_the_title($service_id) ?: sprintf(__('Услуга #%d', 'freeio-wc-service-cart'), $service_id);

            $cart_item_data = [
                CART_ITEM_META_KEY => [
                    'service_id' => $service_id,
                    'price' => $price,
                    'title' => $title,
                ],
            ];

            $cart_item_key = WC()->cart->add_to_cart($proxy_id, 1, 0, [], $cart_item_data);
            if (!$cart_item_key) {
                return false;
            }
        }

        return true;
    }

    /** Override price for cart items that have our service meta (woocommerce_before_calculate_totals). */
    public function override_cart_item_price(\WC_Cart $cart): void {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item[CART_ITEM_META_KEY])) {
                continue;
            }
            $meta = $cart_item[CART_ITEM_META_KEY];
            $cart_item['data']->set_price((float) $meta['price']);
        }
    }

    /**
     * Replace cart item name with service title and link.
     * @param string $name
     * @param array<string, mixed> $cart_item
     * @param string $cart_item_key
     */
    public function override_cart_item_name(string $name, array $cart_item, string $cart_item_key): string {
        if (!isset($cart_item[CART_ITEM_META_KEY])) {
            return $name;
        }
        $meta = $cart_item[CART_ITEM_META_KEY];
        $service_id = (int) $meta['service_id'];
        $title = $meta['title'] ?? '';
        $permalink = get_permalink($service_id);

        if ($permalink) {
            return '<a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a>';
        }
        return esc_html($title);
    }

    /**
     * Replace cart item thumbnail with service featured image.
     * @param string $thumbnail
     * @param array<string, mixed> $cart_item
     * @param string $cart_item_key
     */
    public function override_cart_item_thumbnail(string $thumbnail, array $cart_item, string $cart_item_key): string {
        if (!isset($cart_item[CART_ITEM_META_KEY])) {
            return $thumbnail;
        }
        $service_id = (int) $cart_item[CART_ITEM_META_KEY]['service_id'];
        $service_thumbnail = get_the_post_thumbnail($service_id, 'thumbnail');
        if ($service_thumbnail !== '') {
            return $service_thumbnail;
        }
        return function_exists('wc_placeholder_img') ? wc_placeholder_img('thumbnail') : $thumbnail;
    }

    /**
     * Replace cart item permalink with service URL.
     * @param string $permalink
     * @param array<string, mixed> $cart_item
     * @param string $cart_item_key
     */
    public function override_cart_item_permalink(string $permalink, array $cart_item, string $cart_item_key): string {
        if (!isset($cart_item[CART_ITEM_META_KEY])) {
            return $permalink;
        }
        $service_id = (int) $cart_item[CART_ITEM_META_KEY]['service_id'];
        return (string) get_permalink($service_id) ?: $permalink;
    }

    public function render_shortcode(): string {
        $cart_instance = Service_Cart::instance();
        $cart = $cart_instance->get_cart();
        $notices = $this->get_notices();

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
                                    $thumbnail = function_exists('wc_placeholder_img') ? wc_placeholder_img('thumbnail') : '';
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
                                            title="<?php esc_attr_e('Удалить', 'freeio-wc-service-cart'); ?>">
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
                        <form method="post" action="" class="wc-proceed-to-checkout">
                            <?php wp_nonce_field(self::CHECKOUT_NONCE, '_wpnonce_checkout'); ?>
                            <input type="hidden" name="action" value="<?php echo esc_attr(self::CHECKOUT_ACTION); ?>">
                            <button type="submit" class="checkout-button button btn btn-theme w-100 wc-forward">
                                <?php esc_html_e('Перейти к оплате', 'freeio-wc-service-cart'); ?><i class="flaticon-right-up next"></i>
                            </button>
                        </form>
                    </div>
                </div>
                </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /** @return \WC_Session|null */
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
                'order_error' => __('Не удалось создать заказ. Попробуйте ещё раз.', 'freeio-wc-service-cart'),
                'transfer_error' => __('Не удалось перенести услуги в корзину. Попробуйте ещё раз.', 'freeio-wc-service-cart'),
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
