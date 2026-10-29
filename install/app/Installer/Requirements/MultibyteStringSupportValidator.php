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

namespace Installer\Requirements;

/**
 * Class MultibyteStringSupportValidator provides multi-byte extensions requirement validator.
 *
 * @package Installer\Requirements
 */
final class MultibyteStringSupportValidator extends AbstractValidator
{
    /**
     * @var array<string> Required extensions
     */
    protected $extensions = ['mbstring', 'iconv'];

    /**
     * @var int Extensions requirement conditions
     */
    protected $extensions_mode = self::REQUIRE_ALL;
}
