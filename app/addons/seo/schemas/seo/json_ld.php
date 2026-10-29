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

/**
 * Manual JSON-LD fields in admin (Website → SEO → JSON-LD).
 *
 * Sections and fields must stay aligned with fn_seo_get_schema_org_organization_markup()
 * and backend form names (json_ld[section][field]).
 *
 * Per-field optional keys:
 * - default: string used when the key is absent from stored data (explicit empty string in DB still clears the value).
 */
return [
    'organization' => [
        'fields' => [
            'description' => [
                'default' => '',
            ],
            'telephone' => [
                'default' => '',
            ],
            'email' => [
                'default' => '',
            ],
            'same_as' => [
                'default' => '',
            ],
        ],
    ],
];
