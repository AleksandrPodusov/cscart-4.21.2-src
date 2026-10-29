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

$schema['gift_certificates']['modes']['add']['vendor_only'] = true;
$schema['gift_certificates']['modes']['add']['use_company'] = true;
$schema['gift_certificates']['modes']['add']['page_title'] = 'new_certificate';

$schema['gift_certificates']['modes']['update']['vendor_only'] = true;
$schema['gift_certificates']['modes']['update']['use_company'] = true;
$schema['gift_certificates']['modes']['update']['page_title'] = 'editing_certificate';

$schema['gift_certificates']['modes']['manage']['vendor_only'] = true;
$schema['gift_certificates']['modes']['manage']['use_company'] = true;
$schema['gift_certificates']['modes']['manage']['page_title'] = 'gift_certificates';

return $schema;
