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
    FiscalData1214Objects::ADVANCE            => 'fiscal_data_1214_advance_payment',
    FiscalData1214Objects::FULL_PAYMENT       => 'fiscal_data_1214_full_payment',
    FiscalData1214Objects::FULL_PREPAYMENT    => 'fiscal_data_1214_full_prepayment',
    FiscalData1214Objects::PARTIAL_PREPAYMENT => 'fiscal_data_1214_partial_prepayment',
    FiscalData1214Objects::PARTIAL_PAYMENT    => 'fiscal_data_1214_partial_payment_and_credit',
    FiscalData1214Objects::CREDIT             => 'fiscal_data_1214_credit_with_subsequent_payments',
    FiscalData1214Objects::CREDIT_PAYMENT     => 'fiscal_data_1214_credit_payment',
];
