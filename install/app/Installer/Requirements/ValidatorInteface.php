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
 * Interface ValidatorInteface provides interface of a PHP extensions requirement validator.
 *
 * @package Installer\Requirements
 */
interface ValidatorInteface
{
    /** @var int Any of the extensions is required */
    const REQUIRE_ANY = 0;

    /** @var int All of the extensiosn are required */
    const REQUIRE_ALL = 1;

    /** @var string Extensions missing error code */
    const EXTENSION_MISSING = 'missing';

    /**
     * ValidatorInteface constructor.
     */
    public function __construct();

    /**
     * Provides list of extensions the validator requires.
     *
     * @return string[]
     */
    public function getRequirements();

    /**
     * Provides the extensions check mode.
     *
     * @return int
     */
    public function getRequirementsMode();

    /**
     * Checks if the extensions are installed on the server.
     *
     * @return bool
     */
    public function validate();

    /**
     * Provides validation errors.
     *
     * @return string[]
     */
    public function getErrors();

    /**
     * Sets validation errors.
     *
     * @param string[] $errors
     */
    public function setErrors(array $errors);

    /**
     * Provides validation warnings.
     *
     * @return string[]
     */
    public function getWarnings();

    /**
     * Sets validation warnings.
     *
     * @param string[] $warnings
     */
    public function setWarnings(array $warnings);
}
