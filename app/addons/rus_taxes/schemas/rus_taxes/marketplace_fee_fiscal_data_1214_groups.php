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

use Tygh\Enum\FiscalData1214Objects;

defined('BOOTSTRAP') or die('Access denied');

return [
    'recommended' => [
        FiscalData1214Objects::ADVANCE,
        FiscalData1214Objects::FULL_PAYMENT,
    ],
    'other' => [
        FiscalData1214Objects::FULL_PREPAYMENT,
        FiscalData1214Objects::PARTIAL_PREPAYMENT,
        FiscalData1214Objects::PARTIAL_PAYMENT,
        FiscalData1214Objects::CREDIT,
        FiscalData1214Objects::CREDIT_PAYMENT,
    ],
];
