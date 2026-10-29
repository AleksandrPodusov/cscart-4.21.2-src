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

/**
 * Maps rus_taxes fiscal tag 1214 to ATOL API v4 receipt item payment_method.
 */
return [
    FiscalData1214Objects::FULL_PREPAYMENT    => 'full_prepayment',
    FiscalData1214Objects::PARTIAL_PREPAYMENT => 'prepayment',
    FiscalData1214Objects::ADVANCE            => 'advance',
    FiscalData1214Objects::FULL_PAYMENT       => 'full_payment',
    FiscalData1214Objects::PARTIAL_PAYMENT    => 'partial_payment',
    FiscalData1214Objects::CREDIT             => 'credit',
    FiscalData1214Objects::CREDIT_PAYMENT     => 'credit_payment',
];
