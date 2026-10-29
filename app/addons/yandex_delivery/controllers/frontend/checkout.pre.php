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

defined('BOOTSTRAP') or die('Access denied');

/** @var array $params */
$params = $_REQUEST;

if ($mode === 'update_steps' || $mode === 'shipping_estimation' || $mode === 'checkout' || $mode === 'cart') {
    foreach (PickupPointSelection::sanitize($params['select_pickup_point'] ?? null) as $group_id => $selected_points) {
        foreach ($selected_points as $shipping_id => $pickup_point_id) {
            Tygh::$app['session']['cart']['select_pickup_point'][$group_id][$shipping_id] = $pickup_point_id;
        }
    }

    Tygh::$app['view']->assign('select_pickup_point', Tygh::$app['session']['cart']['select_pickup_point'] ?? []);
}
