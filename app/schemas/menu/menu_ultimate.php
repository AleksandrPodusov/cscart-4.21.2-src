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

$schema['top']['settings']['items']['Stores'] = [
    'href'     => 'settings.manage?section_id=Stores',
    'position' => 410,
    'type'     => 'setting',
];

$schema['top']['administration']['items']['stores'] = [
    'href'     => 'companies.manage',
    'position' => 1350,
    'title'    => __('storefronts'),
    'icon' => 'shopping_cart',
];

if (!fn_check_change_storefront_permission()) {
    unset($schema['top']['settings']['items']['Shippings'], $schema['top']['settings']['items']['Stores'], $schema['top']['settings']['items']['Upgrade_center']);
}

return $schema;
