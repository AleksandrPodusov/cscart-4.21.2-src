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

namespace Tygh\Enum;

use ReflectionClass;

/**
 * FiscalData1214Objects contains possible values for fiscal data tag 1214 (payment type).
 *
 * @package Tygh\Addons\RusTaxes\Enum
 */
class FiscalData1214Objects
{
    const FULL_PREPAYMENT = 1;
    const PARTIAL_PREPAYMENT = 2;
    const ADVANCE = 3;
    const FULL_PAYMENT = 4;
    const PARTIAL_PAYMENT = 5;
    const CREDIT = 6;
    const CREDIT_PAYMENT = 7;

    /**
     * Returns all possible values.
     *
     * @return int[]
     */
    public static function getAll(): array
    {
        $refl = new ReflectionClass(FiscalData1214Objects::class);

        return $refl->getConstants();
    }

    /**
     * Checks if a value exists.
     *
     * @param int $value Check value
     */
    public static function isExists(int $value): bool
    {
        return in_array($value, self::getAll(), true);
    }
}
