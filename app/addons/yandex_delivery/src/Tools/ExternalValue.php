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

namespace Tygh\Addons\YandexDelivery\Tools;

/**
 * Reads values that came from outside the module: Yandex responses, shipping settings, request data.
 *
 * None of those sources guarantee a type, so a field expected to hold a string may arrive as an array or an
 * object. Casting such a value directly produces junk, which is why every read goes through here.
 */
class ExternalValue
{
    /**
     * Reads a field as text.
     *
     * @param array<array-key, string|int|float|bool|array|object|null> $data Source data
     * @param int|string                                                $key  Field name
     *
     * @return string Empty string when the field is missing or is not a scalar
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function text(array $data, $key)
    {
        return isset($data[$key]) && is_scalar($data[$key]) ? (string) $data[$key] : '';
    }

    /**
     * Reads a field as a whole number.
     *
     * @param array<array-key, string|int|float|bool|array|object|null> $data Source data
     * @param int|string                                                $key  Field name
     *
     * @return int Zero when the field is missing or is not a number
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function int(array $data, $key)
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (int) $data[$key] : 0;
    }

    /**
     * Reads a field as a fractional number.
     *
     * @param array<array-key, string|int|float|bool|array|object|null> $data Source data
     * @param int|string                                                $key  Field name
     *
     * @return float Zero when the field is missing or is not a number
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function float(array $data, $key)
    {
        return isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : 0.0;
    }

    /**
     * Reads a nested field as an array.
     *
     * @param array<array-key, string|int|float|bool|array|object|null> $data Source data
     * @param int|string                                                $key  Field name
     *
     * @return array<array-key, string|int|float|bool|array|object|null> Empty array when the field is missing or is not an array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function arr(array $data, $key)
    {
        return isset($data[$key]) && is_array($data[$key]) ? $data[$key] : [];
    }
}
