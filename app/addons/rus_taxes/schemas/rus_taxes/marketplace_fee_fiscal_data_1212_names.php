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
    FiscalData1212Objects::PAYMENT          => 'fiscal_data_1212_payment',
    FiscalData1212Objects::SERVICE          => 'fiscal_data_1212_service',
    FiscalData1212Objects::JOB              => 'fiscal_data_1212_job',
    FiscalData1212Objects::AGENT_COMMISSION => 'fiscal_data_1212_agent_commission',
    FiscalData1212Objects::PAYOUT           => 'fiscal_data_1212_payout',
    FiscalData1212Objects::OTHER            => 'fiscal_data_1212_other',
];
