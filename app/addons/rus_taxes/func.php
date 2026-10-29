<?php
/***************************************************************************
*                                                                          *
*   © 2012 ООО "Эком Системы"                                              *
*                                                                          *
* Это коммерческое программное обеспечение. Только пользователи, которые   *
* приобрели действующую лицензию и согласились с условиями лицензионного   *
* соглашения, могут устанавливать и использовать эту программу.            *
*                                                                          *
****************************************************************************
* ПОЖАЛУЙСТА, ВНИМАТЕЛЬНО ПРОЧТИТЕ ПОЛНЫЙ ТЕКСТ ЛИЦЕНЗИОННОГО СОГЛАШЕНИЯ   *
* В ФАЙЛЕ "copyright.txt", ПРЕДОСТАВЛЕННОМ ВМЕСТЕ С ЭТИМ ДИСТРИБУТИВОМ.    *
***************************************************************************/

use Tygh\Addons\RusTaxes\ServiceProvider;
use Tygh\Addons\RusTaxes\TaxType;
use Tygh\Enum\MarkingCodeFormats;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\FiscalData1212Objects;
use Tygh\Enum\FiscalData1214Objects;
use Tygh\Enum\ObjectStatuses;
use Tygh\Enum\YesNo;
use Tygh\Registry;
use Tygh\Settings;

defined('BOOTSTRAP') or die('Access denied');

/**
 * Handler on install add-on.
 */
function fn_rus_taxes_install()
{
    if (fn_allowed_for('MULTIVENDOR')) {
        fn_rus_taxes_set_marketplace_fee_fiscal_defaults();
    }

    $taxes_exists = db_get_field('SELECT COUNT(*) FROM ?:taxes WHERE status = ?s', 'A');

    if ($taxes_exists) {
        return;
    }

    $destinations = fn_get_destinations();

    $taxes = [
        [
            'tax' => __('rus_taxes.tax.vat22'),
            'regnumber' => '',
            'priority' => 0,
            'address_type' => 'S',
            'status' => 'A',
            'price_includes_tax' => 'Y',
            'tax_type' => TaxType::VAT_22,
            'rate_value' => '22',
            'rate_type' => 'P'
        ],
        [
            'tax' => __('rus_taxes.tax.vat10'),
            'regnumber' => '',
            'priority' => 0,
            'address_type' => 'S',
            'status' => 'D',
            'price_includes_tax' => 'Y',
            'tax_type' => TaxType::VAT_10,
            'rate_value' => '10',
            'rate_type' => 'P'
        ],
        [
            'tax' => __('rus_taxes.tax.vat7'),
            'regnumber' => '',
            'priority' => 0,
            'address_type' => 'S',
            'status' => 'D',
            'price_includes_tax' => 'Y',
            'tax_type' => TaxType::VAT_7,
            'rate_value' => '7',
            'rate_type' => 'P'
        ],
        [
            'tax' => __('rus_taxes.tax.vat5'),
            'regnumber' => '',
            'priority' => 0,
            'address_type' => 'S',
            'status' => 'D',
            'price_includes_tax' => 'Y',
            'tax_type' => TaxType::VAT_5,
            'rate_value' => '5',
            'rate_type' => 'P'
        ],
        [
            'tax' => __('rus_taxes.tax.vat0'),
            'regnumber' => '',
            'priority' => 0,
            'address_type' => 'S',
            'status' => 'D',
            'price_includes_tax' => 'Y',
            'tax_type' => TaxType::VAT_0,
            'rate_value' => '0',
            'rate_type' => 'P'
        ],
    ];

    foreach ($taxes as $tax) {
        $tax['rates'] = [];

        foreach ($destinations as $destination) {
            $tax['rates'][$destination['destination_id']] = [
                'rate_value' => $tax['rate_value'],
                'rate_type' => $tax['rate_type']
            ];
        }

        fn_update_tax($tax, 0);
    }
}

/**
 * The "add_to_cart_before_select_product_simple_data" hook handler.
 *
 * Actions performed:
 *  - Adds product payment subject tag 1212 in field list for selection.
 *
 * @param array<string> $data_fields Product simple data fields
 *
 * @see fn_add_product_to_cart
 *
 * @return void
 */
function fn_rus_taxes_add_to_cart_before_select_product_simple_data(array &$data_fields)
{
    $data_fields[] = 'fiscal_data_1212';
    $data_fields[] = 'mark_code_type';
}

/**
 * The "add_to_cart" hook handler.
 *
 * Actions performed:
 *  - Adds product payment subject tag 1212 and mark code type fields to cart item data.
 *  - Stores marketplace fee payment type tag 1214 in marketplace fee cart item metadata.
 *
 * @param array $cart       Cart data
 * @param int   $product_id Product identifier
 * @param int   $_id        Cart item identifier
 * @param array $_data      Additional simple product data
 *
 * @see fn_add_product_to_cart
 *
 * @return void
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 */
function fn_rus_taxes_add_to_cart(array &$cart, $product_id, $_id, array $_data)
{
    if (!empty($_data['fiscal_data_1212'])) {
        $cart['products'][$_id]['fiscal_data_1212'] = (int) $_data['fiscal_data_1212'];
    }

    if (fn_rus_taxes_is_marketplace_fee_product($product_id)) {
        $cart['products'][$_id]['extra']['marketplace_fee_fiscal_data_1214'] = fn_rus_taxes_normalize_marketplace_fee_fiscal_data_1214(
            Registry::ifGet('addons.rus_taxes.marketplace_fee_fiscal_data_1214', FiscalData1214Objects::FULL_PAYMENT)
        );
    }

    if (empty($_data['mark_code_type'])) {
        return;
    }

    $cart['products'][$_id]['mark_code_type'] = $_data['mark_code_type'];
}

/**
 * The "create_order_details" hook handler.
 *
 * Actions performed:
 *  - Adds product payment subject tag 1212 field to stored order item data.
 *
 * @param int   $order_id      Order identifier to create details for
 * @param array $cart          Cart contents
 * @param array $order_details Ordered product details
 * @param array $extra         Product extra parameters
 * @param int   $k             Cart item identifier
 * @param array $v             Cart item data
 *
 * @see fn_create_order_details
 *
 * @return void
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 */
function fn_rus_taxes_create_order_details($order_id, array $cart, array &$order_details, array $extra, $k, array $v)
{
    if (empty($order_details['extra'])) {
        return;
    }

    $extra = unserialize($order_details['extra']);
    if (!empty($v['fiscal_data_1212'])) {
        $extra['fiscal_data_1212'] = $v['fiscal_data_1212'];
    }
    if (!empty($v['mark_code_type'])) {
        $extra['mark_code_type'] = $v['mark_code_type'];
    }
    $order_details['extra'] = serialize($extra);
}

/**
 * Gets variables for templates with marking data to view.
 *
 * @param array $order Order data
 *
 * @return array
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
 */
function fn_rus_taxes_get_marking_data_variables_for_order(array $order)
{
    if (
        empty($order['products'])
        || empty($order['payment_method']['processor_params']['ffd_version'])
        || $order['payment_method']['processor_params']['ffd_version'] !== '1.2'
    ) {
        return [];
    }

    $marking_extra_data = [];
    $marking_service = ServiceProvider::getDigitalMarkingService();
    $marking_extra_data['marked_products'] = $marking_service->getMarkedProductsDataForOrder($order);

    $marked_products_count = 0;
    foreach ($order['products'] as $item_id => $product) {
        if (
            empty($product['extra']['fiscal_data_1212'])
            || !FiscalData1212Objects::isMarkedObject($product['extra']['fiscal_data_1212'])
        ) {
            continue;
        }

        $marking_extra_data['show_marking_interface'] = true;

        if (
            isset($marking_extra_data['marked_products'][$item_id]['marking_codes_data'])
            && count($marking_extra_data['marked_products'][$item_id]['marking_codes_data']) >= (int) $product['amount']
        ) {
            continue;
        }

        $marking_extra_data['show_marking_codes_notification'] = true;
        $marking_extra_data['marked_products_count'] = ++$marked_products_count;
    }

    return $marking_extra_data;
}

/**
 * The "delete_order" hook handler.
 *
 * Actions performed:
 *  - Deletes marking codes data for deleted order.
 *
 * @param int $order_id Order identifier
 *
 * @see fn_delete_order
 *
 * @return void
 */
function fn_rus_taxes_delete_order($order_id)
{
    if (empty($order_id)) {
        return;
    }

    db_query('DELETE FROM ?:order_digital_marking_codes WHERE order_id = ?i', $order_id);
}

/**
 * The "update_order" hook handler.
 *
 * Actions performed:
 *  - Recalculates mark codes for order items by amount.
 *  - Removes redundant mark codes if some order items changed.
 *
 * @param array $order    Order data
 * @param int   $order_id Order identifier
 *
 * @see fn_update_order
 *
 * @return void
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 */
function fn_rus_taxes_update_order(array $order, $order_id)
{
    $marking_service = ServiceProvider::getDigitalMarkingService();
    $marking_service->recalculateMarkingCodesForOrder($order, $order_id, true);
}

/**
 * The "change_order_status_pre" hook handler.
 *
 * Actions performed:
 *  - Prevents changing order status to the one that sends closing receipt. Shows notification.
 *
 * @param int    $order_id            Parent order identifier
 * @param string $status_to           New parent order status (one char)
 * @param string $status_from         Old parent order status (one char)
 * @param array  $force_notification  Array with notification rules
 * @param bool   $place_order         True, if this function have been called inside of fn_place_order function.
 * @param array  $order_info          Order information
 * @param bool   $allow_status_update Whether to allow status update
 *
 * @see fn_update_order
 *
 * @return void
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 */
function fn_rus_taxes_change_order_status_pre(
    $order_id,
    $status_to,
    $status_from,
    array $force_notification,
    $place_order,
    array $order_info,
    &$allow_status_update
) {
    if (
        empty($order_info['payment_method']['processor_params']['final_success_status'])
        || $order_info['payment_method']['processor_params']['final_success_status'] !== $status_to
        || empty($order_info['payment_method']['processor_params']['ffd_version'])
        || $order_info['payment_method']['processor_params']['ffd_version'] !== '1.2'
        || empty($order_info['products'])
    ) {
        return;
    }

    $marking_service = ServiceProvider::getDigitalMarkingService();
    $marked_products = $marking_service->getMarkedProductsDataForOrder($order_info);
    foreach ($order_info['products'] as $item_id => $product) {
        if (
            empty($product['extra']['fiscal_data_1212'])
            || !in_array($product['extra']['fiscal_data_1212'], FiscalData1212Objects::getMarkedObjects())
        ) {
            continue;
        }

        if (
            empty($marked_products[$item_id]['marking_codes_data'])
            || count($marked_products[$item_id]['marking_codes_data']) < (int) $product['amount']
        ) {
            $order_statuses_descriptions = fn_get_simple_statuses();
            fn_set_notification(
                NotificationSeverity::WARNING,
                __('warning'),
                __(
                    'rus_taxes.empty_mark_codes_notification',
                    [
                        '[status_to]' => $order_statuses_descriptions[$status_to],
                        '[order_id]' => $order_id,
                        '[link]' => fn_url('digital_marking.marking_codes?order_id=' . $order_id)
                    ]
                )
            );
            $allow_status_update = false;

            return;
        }
    }
}

/**
 * @param array{name: string, text: string, field: string, disabled?: string} $fields Product fields
 *
 * @see fn_get_product_fields()
 */
function fn_rus_taxes_get_product_fields(array &$fields): void
{
    $fields[] =  [
        'name'  => '[data][fiscal_data_1212]',
        'text'  => __('payment_object'),
        'field' => 'fiscal_data_1212',
    ];

    $fields[] =  [
        'name'  => '[data][mark_code_type]',
        'text'  => __('is_fur_ware'),
        'field' => 'mark_code_type',
    ];
}

/**
 * @param array{mark_code_type?: string, fiscal_data_1212?: int} $params    Product search params
 * @param array<string>                                          $fields    List of fields for retrieving
 * @param array<string, string>                                  $sortings  Sorting fields
 * @param string                                                 $condition String containing SQL-query condition possibly prepended with a logical operator (AND or OR)
 */
function fn_rus_taxes_get_products(array $params, array $fields, array $sortings, &$condition): void
{
    if (!empty($params['mark_code_type'])) {
        $condition .= db_quote(' AND products.mark_code_type = ?s', YesNo::isTrue($params['mark_code_type']) ? MarkingCodeFormats::FUR : '');
    }

    if (!empty($params['fiscal_data_1212']) && FiscalData1212Objects::isExists($params['fiscal_data_1212'])) {
        $condition .= db_quote(' AND products.fiscal_data_1212 = ?i', $params['fiscal_data_1212']);
    }
}

/**
 * Whether marketplace fee fiscal settings are available in the current environment.
 *
 * @return bool
 */
function fn_rus_taxes_is_marketplace_fee_available()
{
    return fn_allowed_for('MULTIVENDOR')
        && Registry::ifGet('addons.vendor_debt_payout.status', ObjectStatuses::DISABLED) === ObjectStatuses::ACTIVE;
}

/**
 * Whether the product is the marketplace fee payout product.
 *
 * @param int|string|null $product_id Product identifier
 *
 * @return bool
 */
function fn_rus_taxes_is_marketplace_fee_product($product_id)
{
    if (!fn_rus_taxes_is_marketplace_fee_available()) {
        return false;
    }

    return (int) $product_id === fn_vendor_debt_payout_get_payout_product();
}

/**
 * Saves marketplace fee fiscal settings from the fee product update form.
 *
 * @param array $settings Marketplace fee settings
 *
 * @return void
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 */
function fn_rus_taxes_save_marketplace_fee_settings(array $settings)
{
    if (!fn_rus_taxes_is_marketplace_fee_available()) {
        return;
    }

    $settings_manager = Settings::instance();
    $settings_manager->updateValue(
        'marketplace_fee_send_receipt',
        !empty($settings['send_receipt']) && YesNo::toBool($settings['send_receipt']) ? YesNo::YES : YesNo::NO,
        'rus_taxes'
    );
    $settings_manager->updateValue(
        'marketplace_fee_fiscal_data_1214',
        (string) fn_rus_taxes_normalize_marketplace_fee_fiscal_data_1214($settings['fiscal_data_1214'] ?? null),
        'rus_taxes'
    );
}

/**
 * Sets default fiscal settings on marketplace fees product when vendor_debt_payout is active.
 *
 * @return void
 */
function fn_rus_taxes_set_marketplace_fee_fiscal_defaults()
{
    if (!fn_rus_taxes_is_marketplace_fee_available()) {
        return;
    }

    $product_id = fn_vendor_debt_payout_get_payout_product();

    if (!$product_id) {
        return;
    }

    db_query(
        'UPDATE ?:products SET ?u WHERE product_id = ?i AND product_type = ?s',
        [
            'fiscal_data_1212' => FiscalData1212Objects::SERVICE,
        ],
        $product_id,
        'D'
    );

    fn_rus_taxes_save_marketplace_fee_settings([
        'send_receipt'     => YesNo::NO,
        'fiscal_data_1214' => FiscalData1214Objects::FULL_PAYMENT,
    ]);
}

/**
 * Sets default fiscal tag 1212 (Service) on marketplace fees product after related add-ons are installed.
 *
 * @param string $addon Installed add-on identifier
 *
 * @return void
 */
function fn_rus_taxes_install_addon_post($addon)
{
    if (!fn_allowed_for('MULTIVENDOR') || !in_array($addon, ['vendor_debt_payout', 'rus_taxes'], true)) {
        return;
    }

    fn_rus_taxes_set_marketplace_fee_fiscal_defaults();
}

/**
 * Normalizes marketplace fee product fiscal tag 1212 value.
 *
 * @param int|string|null $fiscal_data_1212 Fiscal tag 1212 value
 *
 * @return int
 */
function fn_rus_taxes_normalize_marketplace_fee_fiscal_data_1212($fiscal_data_1212)
{
    $fiscal_data_1212 = isset($fiscal_data_1212) ? (int) $fiscal_data_1212 : FiscalData1212Objects::SERVICE;
    $allowed_values = [];
    foreach (fn_get_schema('rus_taxes', 'marketplace_fee_fiscal_data_1212_groups') as $codes) {
        $allowed_values = array_merge($allowed_values, $codes);
    }

    return in_array($fiscal_data_1212, $allowed_values, true)
        ? $fiscal_data_1212
        : FiscalData1212Objects::SERVICE;
}

/**
 * Normalizes marketplace fee fiscal tag 1214 value.
 *
 * @param int|string|null $fiscal_data_1214 Fiscal tag 1214 value
 *
 * @return int
 */
function fn_rus_taxes_normalize_marketplace_fee_fiscal_data_1214($fiscal_data_1214)
{
    $fiscal_data_1214 = isset($fiscal_data_1214) ? (int) $fiscal_data_1214 : FiscalData1214Objects::FULL_PAYMENT;
    $allowed_values = [];
    foreach (fn_get_schema('rus_taxes', 'marketplace_fee_fiscal_data_1214_groups') as $codes) {
        $allowed_values = array_merge($allowed_values, $codes);
    }

    return in_array($fiscal_data_1214, $allowed_values, true)
        ? $fiscal_data_1214
        : FiscalData1214Objects::FULL_PAYMENT;
}

/**
 * Validates and normalizes marketplace fee product fiscal settings before save.
 *
 * @param array  $product_data Product data
 * @param int    $product_id   Product identifier
 * @param string $lang_code    Language code
 * @param bool   $can_update   Whether update is allowed
 *
 * @return void
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 */
function fn_rus_taxes_update_product_pre(array &$product_data, &$product_id, &$lang_code, &$can_update)
{
    if (!fn_rus_taxes_is_marketplace_fee_product($product_id)) {
        return;
    }

    if (isset($product_data['fiscal_data_1212'])) {
        $product_data['fiscal_data_1212'] = fn_rus_taxes_normalize_marketplace_fee_fiscal_data_1212(
            $product_data['fiscal_data_1212']
        );
    }
}

/**
 * The "update_product_post" hook handler.
 *
 * Actions performed:
 *  - Saves marketplace fee fiscal settings from the fee product update form.
 *
 * @param array  $product_data Product data
 * @param int    $product_id   Product identifier
 * @param string $lang_code    Language code
 * @param bool   $create       Whether the product was created
 *
 * @return void
 *
 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
 */
function fn_rus_taxes_update_product_post(array $product_data, $product_id, $lang_code, $create)
{
    if (
        !fn_rus_taxes_is_marketplace_fee_product($product_id)
        || empty($_REQUEST['rus_taxes_marketplace_fee'])
        || !is_array($_REQUEST['rus_taxes_marketplace_fee'])
    ) {
        return;
    }

    fn_rus_taxes_save_marketplace_fee_settings($_REQUEST['rus_taxes_marketplace_fee']);
}
