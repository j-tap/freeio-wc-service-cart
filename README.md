# Freeio WC Service Cart

Плагин интеграции корзины услуг Freeio с WooCommerce: несколько услуг — одна оплата. WooCommerce используется только как платёжный слой, заказы Freeio создаются после оплаты.

## Требования

- WordPress 6+
- WooCommerce 8+
- PHP 8.1+
- Тема **Freeio** (обязательно; без неё плагин не активируется)

## Установка

1. Скопировать папку `freeio-wc-service-cart` в `wp-content/plugins/`.
2. Активировать плагин в админке WordPress.
3. Создать страницу с шорткодом `[freeio_service_cart]`.
4. Указать эту страницу в настройках: **Freeio Settings → Service Cart** — выбрать страницу корзины в выпадающем списке и сохранить.

   Родительское меню Freeio в теме имеет slug `freelancer-settings`. Переопределить: `add_filter('freeio_wc_service_cart_settings_parent_slug', fn() => 'другой-slug');`

   Альтернатива (через код): опция `freeio_wc_service_cart_page_id`, либо фильтр для URL:

```php
add_filter('freeio_wc_service_cart_page_url', fn() => 'https://yoursite.com/service-cart/');
```

## Добавление услуги в корзину

### Шорткод кнопки

На странице услуги (или в виджете) вставьте:

```
[freeio_add_to_cart service_id="123"]
```

Параметры (все кроме `service_id` опциональны):

| Параметр    | Описание |
|------------|----------|
| `service_id` | ID услуги (обязательно) |
| `package`    | Ключ пакета (service_package) |
| `addons`    | ID дополнений через запятую, например `addons="1,2,3"` |
| `text`      | Текст кнопки (по умолчанию «Добавить в корзину») |
| `class`     | CSS-классы кнопки (по умолчанию `freeio-add-to-cart-btn button`) |

Примеры:

```
[freeio_add_to_cart service_id="15"]
[freeio_add_to_cart service_id="15" package="premium" text="Заказать"]
[freeio_add_to_cart service_id="15" addons="1,2" class="btn btn-primary"]
```

Форма отправляется на **текущую страницу** (страницу услуги). POST перехватывается на самом раннем хуке `init` (приоритет 0), до вывода темы, и выполняется редирект на страницу корзины. Отдельный URL `/freeio-add-to-cart/` не используется при нажатии кнопки — пустая страница по этому адресу на работу кнопки не влияет.

### Ссылка на корзину в шапке

Шорткод **`[freeio_cart_link]`** — ссылка на страницу корзины с иконкой и количеством позиций (для шапки/меню).

Параметры (все опциональны):

| Параметр    | Описание | По умолчанию |
|------------|----------|--------------|
| `class`    | CSS-класс ссылки | `freeio-cart-link` |
| `icon`     | Показать иконку корзины (`yes`/`no`) | `yes` |
| `show_count` | Показать количество товаров | `yes` |
| `icon_class` | Класс иконки (в теме Freeio — Flaticon, например `flaticon-shopping-cart`) | `flaticon-shopping-cart` |

Примеры:

```
[freeio_cart_link]
[freeio_cart_link class="header-cart" icon_class="flaticon-shopping-cart"]
[freeio_cart_link icon_class="flaticon-cart" show_count="no"]
```

Иконка выводится как `<span class="freeio-cart-link-icon flaticon-shopping-cart">` — подключается шрифт/стили Flaticon темы. Если в теме другой класс иконки корзины, задайте его в `icon_class`.

### Своя форма (POST)

Форма POST на любой URL с полями:

- `action=freeio_add_service_to_cart`
- `_wpnonce` (wp_nonce_field для действия `freeio_add_service_to_cart`)
- `service_id` (обязательно)
- `service_package` (опционально)
- `service_addons[]` (опционально, массив ID дополнений)

## Интеграция с Freeio

### Расчёт цены

Плагин пытается получить цену через класс `WP_Freeio_Service_Meta` (если есть). Переопределить расчёт можно фильтром:

```php
add_filter('freeio_wc_service_cart_calculate_price', function ($price, $service_id, $package_key, $addons) {
    // вернуть (float) цену или null при ошибке
    return 99.00;
}, 10, 4);
```

### Создание заказов Freeio после оплаты

После успешной оплаты WooCommerce плагин вызывает действие для каждого элемента корзины:

```php
add_action('freeio_wc_service_cart_create_freeio_order', function ($cart_item, $wc_order_id, $wc_order) {
    // $cart_item: service_id, package_key, addons, calculated_price
    // Создать заказ Freeio и сохранить в его meta: wc_order_id = $wc_order_id
}, 10, 3);
```

Дополнительно доступны:

- `freeio_wc_service_cart_before_orders_sync` — перед созданием заказов (передаётся $order, $cart).
- `freeio_wc_service_cart_after_orders_sync` — после создания заказов и очистки корзины.

## Структура элемента корзины

- `service_id` (int)
- `package_key` (string|null)
- `addons` (array<int>)
- `calculated_price` (float)

Одинаковые наборы в корзине не объединяются.

## Переводы

Плагин переводится через текстовый домен `freeio-wc-service-cart`. Поддерживаются:

- **ru_RU** — русский
- **en_US** — английский
- **es_ES** — испанский

Файлы переводов: `languages/freeio-wc-service-cart-{locale}.po` и скомпилированные `.mo`. После правки `.po` пересобрать `.mo`:

```bash
msgfmt -o languages/freeio-wc-service-cart-ru_RU.mo languages/freeio-wc-service-cart-ru_RU.po
```
