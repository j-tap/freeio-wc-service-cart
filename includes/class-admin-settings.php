<?php

declare(strict_types=1);

namespace FreeioWcServiceCart;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Страница настроек в админке: выбор страницы корзины услуг.
 */
final class Admin_Settings {

    private const PAGE_SLUG = 'freeio-wc-service-cart';
    private const OPTION_NAME = 'freeio_wc_service_cart_page_id';

    private static ?self $instance = null;

    public static function instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_menu'], 99);
        add_action('admin_init', [$this, 'register_setting']);
    }

    public function register_menu(): void {
        $parent_slug = (string) apply_filters('freeio_wc_service_cart_settings_parent_slug', 'freelancer-settings');
        add_submenu_page(
            $parent_slug,
            __('Freeio Service Cart', 'freeio-wc-service-cart'),
            __('Service Cart', 'freeio-wc-service-cart'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    public function register_setting(): void {
        register_setting(
            'freeio_wc_service_cart_settings',
            self::OPTION_NAME,
            [
                'type' => 'integer',
                'default' => 0,
                'sanitize_callback' => 'absint',
            ]
        );
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $page_id = (int) get_option(self::OPTION_NAME, 0);
        $saved = isset($_GET['settings-updated']);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Настройки сохранены.', 'freeio-wc-service-cart'); ?></p></div>
            <?php endif; ?>
            <form action="options.php" method="post">
                <?php settings_fields('freeio_wc_service_cart_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(self::OPTION_NAME); ?>"><?php esc_html_e('Страница корзины услуг', 'freeio-wc-service-cart'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'id' => self::OPTION_NAME,
                                'name' => self::OPTION_NAME,
                                'selected' => $page_id,
                                'show_option_none' => __('— Выберите страницу —', 'freeio-wc-service-cart'),
                                'option_none_value' => '0',
                            ]);
                            ?>
                            <p class="description"><?php esc_html_e('Страница, на которой размещён шорткод [freeio_service_cart]. После добавления услуги в корзину пользователь перенаправляется на эту страницу.', 'freeio-wc-service-cart'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
