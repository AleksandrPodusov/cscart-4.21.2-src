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

defined('BOOTSTRAP') or die('Access denied');

/** @var array $params */
$params = $_REQUEST;

if ($mode === 'update_steps') {
    if (!empty($params['select_pickup_point'])) {
        foreach ($params['select_pickup_point'] as $g_id => $select) {
            foreach ($select as $s_id => $o_id) {
                Tygh::$app['session']['cart']['select_pickup_point'][$g_id][$s_id] = $o_id;
            }
        }
    }
}

if ($mode === 'checkout') {
    if (!empty($params['select_pickup_point'])) {
        foreach ($params['select_pickup_point'] as $g_id => $select) {
            foreach ($select as $s_id => $o_id) {
                Tygh::$app['session']['cart']['select_pickup_point'][$g_id][$s_id] = $o_id;
            }
        }
    }
}
