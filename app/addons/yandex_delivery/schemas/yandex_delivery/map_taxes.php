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

use Tygh\Addons\RusTaxes\TaxType;

// Yandex accepts 0, 5, 7, 10, 22 and -1 for "no VAT". Rates it dropped have no counterpart, so they
// report -1 until the store converts them; anything absent here reports -1 as well.
return [
    TaxType::NONE    => -1,
    TaxType::VAT_0   => 0,
    TaxType::VAT_5   => 5,
    TaxType::VAT_7   => 7,
    TaxType::VAT_10  => 10,
    TaxType::VAT_22  => 22,
    TaxType::VAT_105 => 5,
    TaxType::VAT_107 => 7,
    TaxType::VAT_110 => 10,
    TaxType::VAT_122 => 22,
    TaxType::VAT_20  => -1,
    TaxType::VAT_120 => -1,
];
