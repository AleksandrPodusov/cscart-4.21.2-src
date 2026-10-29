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
 * Class XmlSupportValidator provides XML extensions requirement validator.
 *
 * @package Installer\Requirements
 */
final class XmlSupportValidator extends AbstractValidator
{
    protected $extensions = ['xml', 'libxml', 'SimpleXML', 'dom', 'xmlreader', 'xmlwriter'];

    protected $extensions_mode = self::REQUIRE_ALL;
}
