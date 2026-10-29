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
 * Class ImageProcessingValidator provides ImageMagick and GD extensions requirement validator.
 *
 * @package Installer\Requirements
 */
final class ImageProcessingValidator extends AbstractValidator
{
    protected $extensions = ['imagick', 'gd'];

    protected $extensions_mode = self::REQUIRE_ANY;

    public function validate()
    {
        $result = parent::validate();

        if (in_array('gd', self::$installed_extensions) && !in_array('imagick', self::$installed_extensions)) {
            $this->warnings[] = 'bad_lib';
        }

        return $result;
    }
}
