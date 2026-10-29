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

defined('BOOTSTRAP') or die('Access denied');

if ($mode !== 'configure' || ExternalValue::text($_REQUEST, 'module') !== 'yandex_delivery') {
    return [CONTROLLER_STATUS_OK];
}

$shipping_id = ExternalValue::int($_REQUEST, 'shipping_id');
$service_params = ExternalValue::arr((array) Tygh::$app['view']->getTemplateVars('shipping'), 'service_params');

// Asked only while the city is still unset: a saved one always wins, and without a token there is nobody to ask.
if (
    $shipping_id === 0
    || ExternalValue::text($service_params, 'auth_token') === ''
    || ExternalValue::int($service_params, 'dropoff_geo_id') !== 0
) {
    return [CONTROLLER_STATUS_OK];
}

/** @var \Tygh\Addons\YandexDelivery\Services\WarehousePickerService $warehouse_picker_service */
$warehouse_picker_service = Tygh::$app['addons.yandex_delivery.warehouse_picker_service'];

Tygh::$app['view']->assign(
    'yandex_delivery_default_city',
    $warehouse_picker_service->getDefaultCityObject($shipping_id)
);

return [CONTROLLER_STATUS_OK];
