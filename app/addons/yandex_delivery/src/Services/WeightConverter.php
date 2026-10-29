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

namespace Tygh\Addons\YandexDelivery\Services;

/**
 * Converts product weight into the grams Yandex expects.
 *
 * The store keeps weight in its own unit and only knows how many grams that unit holds, so the factor comes from
 * outside and this class stays unaware of where the setting lives.
 */
class WeightConverter
{
    /** Yandex rejects a parcel with no weight, so an item without one is reported as this many grams. */
    const MINIMUM_GRAMS = 100;

    /** @var float Grams in one unit of the weight the store operates in */
    protected $grams_in_unit;

    /** @var int Weight reported for an item that has none */
    protected $minimum_grams;

    /**
     * @param string|int|float $grams_in_unit Grams in one unit of the weight the store operates in
     * @param string|int|float $minimum_grams Weight reported for an item that has none
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function __construct($grams_in_unit, $minimum_grams = self::MINIMUM_GRAMS)
    {
        $this->grams_in_unit = (float) $grams_in_unit;
        $this->minimum_grams = (int) $minimum_grams;
    }

    /**
     * Converts a weight into whole grams, never below the minimum.
     *
     * @param string|int|float $weight Weight in the unit the store operates in
     *
     * @return int
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function toGrams($weight)
    {
        $grams = (float) $weight * $this->grams_in_unit;

        return max((int) ceil($grams), $this->minimum_grams);
    }
}
