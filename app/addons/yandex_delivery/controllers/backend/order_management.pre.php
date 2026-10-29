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

use Tygh\Addons\YandexDelivery\Tools\PickupPointSelection;
use Tygh\Enum\OrderDataTypes;

defined('BOOTSTRAP') or die('Access denied');

if ($mode === 'update' && !isset($_REQUEST['is_ajax'])) {
    $cart = Tygh::$app['session']['cart'];

    if (!empty($cart['order_id'])) {
        $old_shipping_data = db_get_field(
            'SELECT data FROM ?:order_data WHERE order_id = ?i AND type = ?s',
            $cart['order_id'],
            OrderDataTypes::SHIPPING
        );

        if (!empty($old_shipping_data)) {
            $old_shipping_data = unserialize($old_shipping_data);

            if (is_array($old_shipping_data)) {
                foreach ($old_shipping_data as $shipping) {
                    if (($shipping['module'] ?? '') !== 'yandex_delivery' || empty($shipping['pickup_point_id'])) {
                        continue;
                    }

                    Tygh::$app['session']['cart']['select_pickup_point'][$shipping['group_key']][$shipping['shipping_id']]
                        = $shipping['pickup_point_id'];
                }
            }
        }
    }
}

if ($mode === 'update_shipping' && !empty($_REQUEST['shipping_ids']) && is_array($_REQUEST['shipping_ids'])) {
    $selection = PickupPointSelection::sanitize($_REQUEST['select_pickup_point'] ?? null);

    foreach ($_REQUEST['shipping_ids'] as $group_key => $shipping_id) {
        if (!is_scalar($group_key) || !is_numeric($shipping_id)) {
            continue;
        }

        $point_id = $selection[(string) $group_key][(int) $shipping_id] ?? '';
        if ($point_id === '') {
            continue;
        }

        Tygh::$app['session']['cart']['select_pickup_point'][$group_key][$shipping_id] = $point_id;
    }
}
