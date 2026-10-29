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

use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Enum\NotificationSeverity;

defined('BOOTSTRAP') or die('Access denied');

/** @var array<string, string|int|float|bool|array|object|null> $picker_params */
$picker_params = $_REQUEST;

$yandex_delivery_ajax_modes = [
    'check_connection',
    'check_warehouse_pickups',
    'get_warehouses_list',
    'get_dropoff_points_list',
    'get_cities_list',
];

if (in_array($mode, $yandex_delivery_ajax_modes, true) && !defined('AJAX_REQUEST')) {
    return [CONTROLLER_STATUS_REDIRECT, fn_url()];
}

if ($mode === 'check_connection') {
    $service_params = [];
    foreach (['auth_token', 'test_mode'] as $field) {
        $value = $_REQUEST[$field] ?? '';
        $service_params[$field] = is_string($value) ? $value : '';
    }

    /** @var \Tygh\Addons\YandexDelivery\Services\ConnectionChecker $connection_checker */
    $connection_checker = Tygh::$app['addons.yandex_delivery.connection_checker'];
    $result = $connection_checker->check($service_params);

    $titles = [
        NotificationSeverity::NOTICE => 'notice',
        NotificationSeverity::ERROR  => 'error',
    ];

    fn_set_notification(
        $result['severity'],
        __($titles[$result['severity']] ?? 'notice'),
        $result['message']
    );

    return [CONTROLLER_STATUS_NO_CONTENT];
}

if ($mode === 'check_warehouse_pickups') {
    // Diagnosed from what is in the form, not from what is saved: the warehouse is usually picked and judged
    // before the settings are saved.
    $service_params = [];
    foreach (['auth_token', 'test_mode', 'use_dropoff', 'source_platform_station_id'] as $field) {
        $service_params[$field] = ExternalValue::text($_REQUEST, $field);
    }

    /** @var \Tygh\Addons\YandexDelivery\Services\PickupDiagnostics $pickup_diagnostics */
    $pickup_diagnostics = Tygh::$app['addons.yandex_delivery.pickup_diagnostics'];
    $verdict = $pickup_diagnostics->getVerdict($service_params);

    Tygh::$app['ajax']->assign('message', $verdict === '' ? '' : __($verdict));

    return [CONTROLLER_STATUS_NO_CONTENT];
}

if (in_array($mode, ['get_warehouses_list', 'get_dropoff_points_list', 'get_cities_list'], true)) {
    /** @var \Tygh\Addons\YandexDelivery\Services\WarehousePickerService $warehouse_picker_service */
    $warehouse_picker_service = Tygh::$app['addons.yandex_delivery.warehouse_picker_service'];
    $shipping_id = ExternalValue::int($_REQUEST, 'shipping_id');

    // The token is taken from the shipping method, so asking about someone else's method would query Yandex on
    // their account. Marketplace administrators pass this check by design.
    if ($shipping_id > 0 && !fn_check_company_id('shippings', 'shipping_id', $shipping_id)) {
        return [CONTROLLER_STATUS_DENIED];
    }

    try {
        if ($mode === 'get_dropoff_points_list') {
            [$objects, $total_objects] = $warehouse_picker_service->getDropoffWarehouseObjects($shipping_id, $picker_params);
        } elseif ($mode === 'get_cities_list') {
            [$objects, $total_objects] = $warehouse_picker_service->getCityObjects($shipping_id, $picker_params);
        } else {
            [$objects, $total_objects] = $warehouse_picker_service->getShippingWarehouseObjects($shipping_id, $picker_params);
        }
    } catch (Exception $e) {
        $objects = [];
        $total_objects = 0;
    }

    Tygh::$app['ajax']->assign('objects', $objects);
    Tygh::$app['ajax']->assign('total_objects', $total_objects);

    return [CONTROLLER_STATUS_NO_CONTENT];
}
