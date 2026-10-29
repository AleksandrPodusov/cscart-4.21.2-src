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
 * Class AbstractValidator provides the general PHP extensions requirement validator behavior.
 *
 * @package Installer\Requirements
 */
abstract class AbstractValidator implements ValidatorInteface
{
    /**
     * @var array<string> Required extensions
     */
    protected $extensions = [];

    /**
     * @var int Extensions requirement conditions
     */
    protected $extensions_mode = self::REQUIRE_ANY;

    /**
     * @var array<string> Validation errors
     */
    protected $errors = [];

    /**
     * @var array<string> Validation warnings
     */
    protected $warnings = [];

    /**
     * @var array<string> Installed extensions cache
     */
    protected static $installed_extensions;

    /** @inheritdoc */
    public function __construct()
    {
        if (static::$installed_extensions === null) {
            static::$installed_extensions = get_loaded_extensions();
        }
    }

    /** @inheritdoc */
    public function getRequirements()
    {
        return $this->extensions;
    }

    /** @inheritdoc */
    public function getRequirementsMode()
    {
        return $this->extensions_mode;
    }

    /** @inheritdoc */
    public function validate()
    {
        $match = array_intersect($this->extensions, static::$installed_extensions);

        $result = (bool) $match;
        if ($this->extensions_mode === self::REQUIRE_ALL) {
            $result = $match === $this->extensions;
        }

        if (!$result) {
            $this->errors[] = self::EXTENSION_MISSING;
        }

        return $result;
    }

    /** @inheritdoc */
    public function getErrors()
    {
        return $this->errors;
    }

    /** @inheritdoc */
    public function setErrors(array $errors)
    {
        $this->errors = $errors;
    }

    /** @inheritdoc */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /** @inheritdoc */
    public function setWarnings(array $warnings)
    {
        $this->warnings = $warnings;
    }
}
