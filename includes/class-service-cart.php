<?php

declare(strict_types=1);

namespace FreeioWcServiceCart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Freeio service cart. Stored in WC session; items added via POST/AJAX.
 */
final class Service_Cart {

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private const ADD_TO_CART_SLUG = 'freeio-add-to-cart';
    private const QUERY_VAR = 'freeio_add_to_cart';

    private function __construct() {
        add_action('init', [$this, 'handle_add_to_cart_post_anywhere'], 0);

        add_action('wp_ajax_' . ADD_TO_CART_ACTION, [$this, 'handle_ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_' . ADD_TO_CART_ACTION, [$this, 'handle_ajax_add_to_cart']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        add_shortcode('freeio_add_to_cart', [$this, 'render_add_to_cart_shortcode']);
        add_shortcode('freeio_cart_link', [$this, 'render_cart_link_shortcode']);
    }

    /**
     * No-JS fallback: intercept POST on any page → add to cart + redirect.
     */
    public function handle_add_to_cart_post_anywhere(): void {
        if (wp_doing_ajax()) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        if (!isset($_POST['action']) || $_POST['action'] !== ADD_TO_CART_ACTION) {
            return;
        }
        try {
            nocache_headers();
            $result = $this->process_add_to_cart();

            $url = $result['success']
                ? $this->get_cart_url_with_notice('success', 'added')
                : $this->get_cart_url_with_notice('error', $result['code']);

            $this->redirect_and_exit($url);
        } catch (\Throwable $e) {
            error_log('[freeio-wc-service-cart] Add to cart error: ' . $e->getMessage());
            $this->redirect_and_exit($this->get_cart_url());
        }
    }

    /**
     * AJAX handler: add to cart without page reload.
     */
    public function handle_ajax_add_to_cart(): void {
        try {
            $result = $this->process_add_to_cart();
        } catch (\Throwable $e) {
            error_log('[freeio-wc-service-cart] AJAX add to cart error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Внутренняя ошибка сервера.', 'freeio-wc-service-cart')], 500);
            return;
        }

        $cart_count = count($this->get_cart());

        if ($result['success']) {
            wp_send_json_success([
                'message' => __('Услуга добавлена в корзину.', 'freeio-wc-service-cart'),
                'cart_count' => $cart_count,
                'cart_url' => $this->get_cart_url(),
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message'],
                'code' => $result['code'],
                'cart_count' => $cart_count,
            ]);
        }
    }

    /**
     * @return array{success: bool, code: string, message: string}
     */
    private function process_add_to_cart(): array {
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), NONCE_ACTION)) {
            return [
                'success' => false,
                'code' => 'security',
                'message' => __('Ошибка безопасности. Обновите страницу и попробуйте снова.', 'freeio-wc-service-cart'),
            ];
        }

        if ($this->is_login_required_for_checkout() && !is_user_logged_in()) {
            return [
                'success' => false,
                'code' => 'login_required',
                'message' => __('Нужно авторизоваться для покупки.', 'freeio-wc-service-cart'),
            ];
        }

        $service_id = isset($_POST['service_id']) ? absint($_POST['service_id']) : 0;
        if ($service_id <= 0) {
            return [
                'success' => false,
                'code' => 'invalid_service',
                'message' => __('Услуга не указана.', 'freeio-wc-service-cart'),
            ];
        }

        $package_key = isset($_POST['service_package']) ? sanitize_text_field(wp_unslash($_POST['service_package'])) : null;
        $package_key = ($package_key !== '' && $package_key !== null) ? $package_key : null;

        $addons = [];
        if (!empty($_POST['service_addons']) && is_array($_POST['service_addons'])) {
            $addons = array_map('absint', wp_unslash($_POST['service_addons']));
            $addons = array_values(array_filter($addons));
        }

        $price = $this->calculate_price($service_id, $package_key, $addons);
        if ($price === null || $price < 0) {
            return [
                'success' => false,
                'code' => 'price_error',
                'message' => __('Не удалось рассчитать стоимость услуги.', 'freeio-wc-service-cart'),
            ];
        }

        $this->add_item([
            'service_id' => $service_id,
            'package_key' => $package_key,
            'addons' => $addons,
            'calculated_price' => (float) $price,
        ]);

        return [
            'success' => true,
            'code' => 'added',
            'message' => __('Услуга добавлена в корзину.', 'freeio-wc-service-cart'),
        ];
    }

    public static function register_rewrite_rule(): void {
        add_rewrite_rule(
            self::ADD_TO_CART_SLUG . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    public function enqueue_assets(): void {
        $plugin_url = Plugin::plugin_url();
        $version = PLUGIN_VERSION;

        wp_enqueue_style(
            'freeio-service-cart',
            $plugin_url . 'assets/css/service-cart.css',
            [],
            $version
        );

        wp_enqueue_script(
            'freeio-add-to-cart',
            $plugin_url . 'assets/js/add-to-cart.js',
            [],
            $version,
            true
        );

        wp_localize_script('freeio-add-to-cart', 'freeioServiceCart', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'action' => ADD_TO_CART_ACTION,
            'addingText' => __('Добавляем…', 'freeio-wc-service-cart'),
            'viewCartText' => __('Смотреть корзину', 'freeio-wc-service-cart'),
            'requireLogin' => $this->is_login_required_for_checkout(),
            'isLoggedIn' => is_user_logged_in(),
            'loginRequiredMessage' => __('Нужно авторизоваться для покупки.', 'freeio-wc-service-cart'),
            'loginRequiredPopupMessage' => __('You do not have permission to buy this service. Please log in to continue.', 'freeio-wc-service-cart'),
        ]);
    }

    private const CART_ICON_SVG = '<svg class="freeio-cart-icon" width="32" height="32" viewBox="190 170 300 300" fill="currentColor" aria-hidden="true"><path d="M324.3 203.5c-17.2 4.7-31.3 18.9-35.8 36-.8 3.3-1.5 8.1-1.5 10.7v4.8h-8.7c-14.6.1-21.4 4.9-23.4 16.6-1 6.4-8.9 117.2-9.1 128.2-.1 7.4 1.2 11 5.9 15.4 1.5 1.5 4.1 3.2 5.6 3.7 1.9.8 28.1 1.1 80.8 1.1 76.3 0 78.1 0 81.9-2 4.4-2.2 8.6-7.8 9.5-12.5.8-3.8-8.2-131.7-9.5-136.7-2.6-9.3-10-13.7-23.2-13.8H388v-4.8c0-6.2-2.3-14.8-5.6-21.5-3.4-6.7-14.4-17.7-21.1-21.1-10.9-5.5-25.7-7.1-37-4.1m27.8 17.7c11 5.1 18.8 16.2 19.7 28l.4 5.8h-69.5l.7-5.7c1.6-13.9 11.1-25.7 23.9-29.9 6.7-2.2 18.1-1.3 24.8 1.8M287.5 292c0 11.3.1 21.1.3 21.7.1.7 1.1 2.2 2.2 3.3 2.8 2.8 7.5 2.6 10.5-.5 2.5-2.4 2.5-2.4 2.5-24V271h69v20.6c0 20.8.4 23.4 3.9 26.1 2.6 2 7 1.5 9.6-1.2 2.5-2.4 2.5-2.4 2.5-24V271h8c5.6 0 8.2.4 8.5 1.2.8 2.4 9.5 129.9 9 130.9-.4.5-30.6.9-76 .9-59.3 0-75.4-.3-75.7-1.3-.7-1.9 8.6-129 9.6-130.5.5-.9 3-1.2 8.4-1l7.7.3z"/></svg>';

    /**
     * Shortcode [freeio_cart_link] — cart link with icon and item count (for header).
     * Params: class, icon (yes/no), show_count
     */
    public function render_cart_link_shortcode(array $atts): string {
        $atts = shortcode_atts([
            'class' => 'freeio-cart-link',
            'icon' => 'yes',
            'show_count' => 'yes',
        ], $atts, 'freeio_cart_link');

        $cart = $this->get_cart();
        $count = count($cart);
        $url = $this->get_cart_url();

        $show_icon = filter_var($atts['icon'], FILTER_VALIDATE_BOOLEAN);
        $show_count = filter_var($atts['show_count'], FILTER_VALIDATE_BOOLEAN);

        $inner = '';
        if ($show_icon) {
            $inner .= self::CART_ICON_SVG;
        }
        if ($show_count) {
            $inner .= '<span class="freeio-cart-link-count">' . esc_html((string) $count) . '</span>';
        }
        $label = __('Корзина', 'freeio-wc-service-cart');
        $aria = $count > 0 ? sprintf(/* translators: %d: number of items */ __('Корзина (%d)', 'freeio-wc-service-cart'), $count) : $label;

        $html = '<a href="' . esc_url($url) . '" class="' . esc_attr($atts['class']) . '" title="' . esc_attr($label) . '" aria-label="' . esc_attr($aria) . '">' . $inner . '</a>';
        return $html;
    }

    /**
     * Shortcode [freeio_add_to_cart service_id="123" package="" addons="" text="" class=""]
     * @param array<string, string> $atts
     */
    public function render_add_to_cart_shortcode(array $atts): string {
        $atts = shortcode_atts([
            'service_id' => '0',
            'package' => '',
            'addons' => '',
            'text' => '',
            'class' => 'button product_type_simple add_to_cart_button freeio-add-to-cart-btn',
        ], $atts, 'freeio_add_to_cart');

        $service_id = absint($atts['service_id']);
        if ($service_id <= 0) {
            return '<!-- freeio_add_to_cart: service_id required -->';
        }

        $button_text = $atts['text'] !== ''
            ? $atts['text']
            : __('Добавить в корзину', 'freeio-wc-service-cart');
        $css_class = esc_attr($atts['class']);

        ob_start();
        ?>
        <form class="freeio-add-to-cart-form" method="post" action="" data-cart-url="<?php echo esc_url($this->get_cart_url()); ?>">
            <input type="hidden" name="action" value="<?php echo esc_attr(ADD_TO_CART_ACTION); ?>">
            <?php wp_nonce_field(NONCE_ACTION, '_wpnonce'); ?>
            <input type="hidden" name="service_id" value="<?php echo esc_attr((string) $service_id); ?>">
            <?php if ($atts['package'] !== '') : ?>
                <input type="hidden" name="service_package" value="<?php echo esc_attr($atts['package']); ?>">
            <?php endif; ?>
            <?php
            $addons = array_filter(array_map('absint', explode(',', $atts['addons'])));
            foreach ($addons as $addon_id) {
                echo '<input type="hidden" name="service_addons[]" value="' . esc_attr((string) $addon_id) . '">';
            }
            ?>
            <button type="submit" class="<?php echo $css_class; ?>">
                <span class="freeio-btn-text"><?php echo esc_html($button_text); ?></span><i class="flaticon-right-up next"></i>
            </button>
        </form>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * @return never
     */
    private function redirect_and_exit(string $url): void {
        if ($url === '' || $url === 'false') {
            $url = home_url('/');
        }

        nocache_headers();

        if (!headers_sent()) {
            wp_safe_redirect($url, 302);
            exit;
        }

        $safe = esc_url($url);
        echo '<script>window.location.replace(' . wp_json_encode($safe) . ');</script>';
        echo '<noscript><meta http-equiv="refresh" content="' . esc_attr('0;url=' . $safe) . '"></noscript>';
        exit;
    }

    private function get_cart_url_with_notice(string $type, string $code): string {
        $url = $this->get_cart_url();
        return add_query_arg(['freeio_cart_notice' => $type, 'freeio_cart_code' => $code], $url);
    }

    public function get_cart_url(): string {
        $url = apply_filters('freeio_wc_service_cart_page_url', null);
        if (is_string($url) && $url !== '') {
            return $url;
        }
        $page_id = (int) get_option('freeio_wc_service_cart_page_id', 0);
        if ($page_id > 0) {
            $permalink = get_permalink($page_id);
            if (is_string($permalink) && $permalink !== '') {
                return $permalink;
            }
        }
        return home_url('/');
    }

    /**
     * Whether WooCommerce requires the user to be logged in to checkout (guest checkout disabled).
     */
    private function is_login_required_for_checkout(): bool {
        return get_option('woocommerce_enable_guest_checkout', 'yes') !== 'yes';
    }

    private const PRICE_META_KEYS = ['_price', '_service_price', '_regular_price', 'price'];

    /**
     * @return float|null Price or null on error
     */
    private function calculate_price(int $service_id, ?string $package_key, array $addons): ?float {
        $price = apply_filters('freeio_wc_service_cart_calculate_price', null, $service_id, $package_key, $addons);
        if (is_numeric($price) && (float) $price >= 0) {
            return (float) $price;
        }

        foreach (self::PRICE_META_KEYS as $meta_key) {
            $meta_value = get_post_meta($service_id, $meta_key, true);
            if (is_numeric($meta_value) && (float) $meta_value >= 0) {
                return (float) $meta_value;
            }
        }

        error_log(sprintf(
            '[freeio-wc-service-cart] Could not find price for service #%d. All meta: %s',
            $service_id,
            wp_json_encode(get_post_meta($service_id))
        ));

        return null;
    }

    /**
     * @param array{service_id: int, package_key: string|null, addons: array<int>, calculated_price: float} $item
     */
    public function add_item(array $item): void {
        $item = $this->normalize_item($item);
        if ($item === null) {
            return;
        }

        $session = $this->get_session();
        if ($session === null) {
            return;
        }

        $cart = $this->get_cart();
        $cart[] = $item;
        $session->set(SESSION_KEY, $cart);
    }

    /**
     * @return array{service_id: int, package_key: string|null, addons: array<int>, calculated_price: float}|null
     */
    private function normalize_item(array $item): ?array {
        $service_id = isset($item['service_id']) ? (int) $item['service_id'] : 0;
        if ($service_id <= 0) {
            return null;
        }
        $package_key = isset($item['package_key']) && $item['package_key'] !== '' ? (string) $item['package_key'] : null;
        $addons = isset($item['addons']) && is_array($item['addons']) ? array_map('intval', $item['addons']) : [];
        $calculated_price = isset($item['calculated_price']) ? (float) $item['calculated_price'] : 0.0;
        if ($calculated_price < 0) {
            return null;
        }
        return [
            'service_id' => $service_id,
            'package_key' => $package_key,
            'addons' => array_values(array_filter($addons)),
            'calculated_price' => $calculated_price,
        ];
    }

    /**
     * @return list<array{service_id: int, package_key: string|null, addons: array<int>, calculated_price: float}>
     */
    public function get_cart(): array {
        $session = $this->get_session();
        if ($session === null) {
            return [];
        }

        $raw = $session->get(SESSION_KEY);
        if (!is_array($raw)) {
            return [];
        }

        $cart = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized = $this->normalize_item($item);
            if ($normalized !== null) {
                $cart[] = $normalized;
            }
        }
        return $cart;
    }

    public function clear(): void {
        $session = $this->get_session();
        if ($session !== null) {
            $session->set(SESSION_KEY, []);
        }
    }

    public function get_total(): float {
        $total = 0.0;
        foreach ($this->get_cart() as $item) {
            $total += $item['calculated_price'];
        }
        return $total;
    }

    public function is_empty(): bool {
        return count($this->get_cart()) === 0;
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

    public static function get_option_page_id_key(): string {
        return 'freeio_wc_service_cart_page_id';
    }
}
