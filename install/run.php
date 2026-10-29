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

use Installer\App;

include 'app/Installer/App.php';
App::instance()->init($_REQUEST);

if (defined('CONSOLE') && file_exists('config.php')) {
    if (!defined('DEVELOPMENT')) {
        define('DEVELOPMENT', true);
    }

    $params = include 'config.php';
    if (file_exists('local_conf.php')) {
        $params = include 'local_conf.php';
    }
    $result = App::instance()->dispatch('setup.console', $params, true);
    $status_code = array_pop($result);
    exit($status_code);
} else {
    App::instance()->run($_REQUEST);
}
