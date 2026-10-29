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

return array(
    // Add-on names to be installed
    // If empty will be installed only addons included by default
    'addons' => array(),

    'cart_settings' => array (
        'email' => 'admin@example.com',
        'password' => 'admin',
        'secret_key' => 'YOURVERYSECRETCEY',
        'languages' => array (
            'en', 'da', 'de', 'es', 'fr', 'el', 'it', 'nl', 'ro', 'ru', 'bg', 'no', 'sl',
        ),
        'main_language' => 'en',
        'demo_catalog' => true,
        'theme_name' => 'nova_theme',
        'license_number' => 'CART-1111-1111-1111-1111'
    ),
    'database_settings' => array(
        'host' => 'localhost',
        'name' => '%DB_NAME%',
        'user' => '%DB_USER%',
        'password' => '%DB_PASS%',
        'table_prefix' => 'cscart_',
        'database_backend' => 'mysqli',
        'notify' => false,
        'allow_override' => 'Y',
    ),
    'server_settings' => array (
        'http_host' => '%HTTP_HOST%',
        'http_path' => '',
        'https_host' => '%HTTP_HOST%',
        'https_path' => '',
        'correct_permissions' => true,
    ),
);
