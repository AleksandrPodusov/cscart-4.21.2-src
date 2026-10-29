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

namespace Tygh\Tools;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Class Math contains methods commonly used for mathematic operations.
 *
 * @package Tygh\Tools
 * @since   4.3.5
 */
class Math
{
    /**
     * Floors given number to the given precision.
     *
     * @param float|int $x         Number to floor
     * @param float|int $precision Precision in format: 0.01, 0.1, 1, 10, 100, ...
     *
     * @return float|int
     */
    public static function floorToPrecision($x, $precision)
    {
        try {
            $x_dec = BigDecimal::of((string) $x);
            $precision_dec = BigDecimal::of((string) $precision);

            $div = $x_dec->dividedBy($precision_dec, 0, RoundingMode::FLOOR);
            $result = $div->multipliedBy($precision_dec);

            return self::toNative($result, $precision);
        } catch (\Throwable $e) {
            return self::floorFallback($x, $precision);
        }
    }

    /**
     * Ceils given number to the given precision.
     *
     * @param float|int $x         Number to ceil
     * @param float|int $precision Precision in format: 0.01, 0.1, 1, 10, 100, ...
     *
     * @return float|int
     */
    public static function ceilToPrecision($x, $precision)
    {
        try {
            $x_dec = BigDecimal::of((string) $x);
            $precision_dec = BigDecimal::of((string) $precision);

            $div = $x_dec->dividedBy($precision_dec, 0, RoundingMode::CEILING);
            $result = $div->multipliedBy($precision_dec);

            return self::toNative($result, $precision);
        } catch (\Throwable $e) {
            return self::ceilFallback($x, $precision);
        }
    }

    /**
     * Convert BigDecimal back to native type (int or float)
     *
     * @param BigDecimal $decimal   Decimal formatted number
     * @param float|int  $precision Precision in format: 0.01, 0.1, 1, 10, 100, ...
     *
     * @return float|int
     */
    protected static function toNative(BigDecimal $decimal, $precision)
    {
        try {
            if ($precision >= 1) {
                $as_int = $decimal->toScale(0, RoundingMode::DOWN);

                return (int) $as_int->__toString();
            }
        } catch (\Throwable $e) {
            // ignore, fallback to float
        }

        return (float) $decimal->__toString();
    }

    /**
     * Fallback: floors given number to the given precision.
     *
     * @param float|int $x         Number to floor
     * @param float|int $precision Precision in format: 0.01, 0.1, 1, 10, 100, ...
     *
     * @return float|int
     */
    public static function floorFallback($x, $precision)
    {
        return $precision * self::floor($x / $precision);
    }

    /**
     * Fallback: ceils given number to the given precision.
     *
     * @param float|int $x         Number to ceil
     * @param float|int $precision Precision in format: 0.01, 0.1, 1, 10, 100, ...
     *
     * @return float|int
     */
    public static function ceilFallback($x, $precision)
    {
        $fmod = $x - self::floorFallback($x, $precision);
        if ($fmod > 0) {
            return $x + $precision - $fmod;
        }

        return $x + $precision - $precision;
    }

    /**
     * floor() PHP function replacement that doesn't returns floats like 1.999999...
     *
     * @param int|float $x
     *
     * @return int
     */
    public static function floor($x)
    {
        // (int) (string) emulates floor() call for positive values.
        // This is required because floor((double) 201) may be equal to 200, because of
        // PHP's internal floating point numbers representation (i.e. 201.0 is stored as 200.999999...)
        $is_negative = $x < 0;
        $x = (string) $x;
        // negative values have to be floored down
        if ($is_negative && strpos($x, '.')) {
            list($x, $dec) = explode('.', $x);
            $x = $x - ($dec ? 1 : 0);
        }

        return (int) $x;
    }

    /**
     * Rounds given number to the given decimals number and returns result as string.
     *
     * @param float|int $x        Number to round
     * @param int       $decimals The number of decimal places
     *
     * @return string
     */
    public static function roundToPrecisionString($x, $decimals)
    {
        try {
            $x_dec = BigDecimal::of((string) $x);
            $result = $x_dec->toScale($decimals, RoundingMode::HALF_UP);

            return $result->__toString();
        } catch (\Throwable $e) {
            $rounded = round((float) $x + 0.00000000001, $decimals);
            return sprintf('%.' . $decimals . 'f', $rounded);
        }
    }
}
