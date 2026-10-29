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

namespace Tygh\Addons\YandexDelivery\Tests\Unit\Services;

use Tygh\Addons\YandexDelivery\Services\WeightConverter;
use Tygh\Tests\Unit\ATestCase;

class WeightConverterTest extends ATestCase
{
    /**
     * @param float|int|string $grams_in_unit Grams in one unit of the store weight
     * @param float|int|string $weight        Weight to convert
     * @param int              $expected      Expected weight in grams
     *
     * @dataProvider dpToGrams
     */
    public function testToGrams($grams_in_unit, $weight, $expected)
    {
        $converter = new WeightConverter($grams_in_unit);

        $this->assertSame($expected, $converter->toGrams($weight));
    }

    /**
     * @return array<string, array{0: float|int|string, 1: float|int|string, 2: int}>
     */
    public function dpToGrams()
    {
        return [
            'store weighs in kilograms' => [1000, 2.5, 2500],
            'store weighs in grams'     => [1, 750, 750],
            'store weighs in pounds'    => [453.592, 3, 1361],
            // A parcel Yandex cannot accept in halves, so a fraction of a gram counts as a whole one.
            'fraction rounds up'        => [1000, 0.4001, 401],
            'numeric string'            => [1000, '1.5', 1500],
        ];
    }

    /**
     * @param float|int|string $weight Weight that produces less than the minimum
     *
     * @dataProvider dpMinimum
     */
    public function testWeightBelowMinimumIsReportedAsMinimum($weight)
    {
        $converter = new WeightConverter(1000);

        $this->assertSame(WeightConverter::MINIMUM_GRAMS, $converter->toGrams($weight));
    }

    /**
     * @return array<string, array{0: float|int|string}>
     */
    public function dpMinimum()
    {
        return [
            'no weight set'      => [0],
            'empty value'        => [''],
            'not a number'       => ['heavy'],
            'lighter than 100 g' => [0.05],
            'negative weight'    => [-1],
        ];
    }

    public function testMinimumIsConfigurable()
    {
        $converter = new WeightConverter(1000, 500);

        $this->assertSame(500, $converter->toGrams(0.1));
        $this->assertSame(700, $converter->toGrams(0.7));
    }
}
