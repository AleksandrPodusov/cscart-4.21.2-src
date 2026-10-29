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

use Tygh\Enum\FiscalData1212Objects;

defined('BOOTSTRAP') or die('Access denied');

return [
    FiscalData1212Objects::COMMODITY                   => 'fiscal_data_1212_commodity',
    FiscalData1212Objects::EXCISE                      => 'fiscal_data_1212_excise',
    FiscalData1212Objects::JOB                         => 'fiscal_data_1212_job',
    FiscalData1212Objects::SERVICE                     => 'fiscal_data_1212_service',
    FiscalData1212Objects::PAYMENT                     => 'fiscal_data_1212_payment',
    FiscalData1212Objects::WITH_MARKING_CODE           => 'fiscal_data_1212_product_with_marking_code',
    FiscalData1212Objects::EXCISABLE_WITH_MARKING_CODE => 'fiscal_data_1212_excisable_product_with_marking_code'
];
