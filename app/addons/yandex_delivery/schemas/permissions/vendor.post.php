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

/** @var array $schema Permissions schema being extended */

// A vendor sets up its own shipping method with its own token, so the helpers behind that form have to answer.
$schema['controllers']['yandex_delivery'] = [
    'modes' => [
        'check_connection' => [
            'permissions' => true,
        ],
        'check_warehouse_pickups' => [
            'permissions' => true,
        ],
        'get_warehouses_list' => [
            'permissions' => true,
        ],
        'get_dropoff_points_list' => [
            'permissions' => true,
        ],
        'get_cities_list' => [
            'permissions' => true,
        ],
    ],
    'permissions' => false,
];

return $schema;
