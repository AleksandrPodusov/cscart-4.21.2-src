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

use Tygh\Addons\YandexDelivery\Enum\DeliveryType;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Addons\YandexDelivery\Services\WeightConverter;
use Tygh\Enum\YesNo;
use Tygh\Tests\Unit\ATestCase;
use Exception;

class ShippingDataBuilderTest extends ATestCase
{
    /** @var ShippingDataBuilder */
    protected $builder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireMockFunction('__');
        $this->builder = new ShippingDataBuilder(new WeightConverter(1000));
    }

    /**
     * @param float|int|string $dimension Dimension in centimeters
     * @param int              $expected  Expected dimension
     *
     * @dataProvider dpNormalizeDimension
     */
    public function testDimensionIsAWholeNumberOfCentimeters($dimension, $expected)
    {
        $this->assertSame($expected, $this->builder->normalizeDimension($dimension));
    }

    /**
     * @return array<string, array{0: float|int|string, 1: int}>
     */
    public function dpNormalizeDimension()
    {
        return [
            'whole number stays'   => [10, 10],
            'fraction is cut off'  => [10.2, 10],
            'numeric string'       => ['15.5', 15],
            // A shop typing a comma used to have the value read as one centimetre and sent to Yandex as such.
            'comma as a separator' => ['10,2', 10],
            'not a number at all'  => ['abc', 0],
            'empty string'         => ['', 0],
            'smaller than 1 cm'    => [0.3, 0],
            'no dimension'         => [0, 0],
            'negative dimension'   => [-5, 0],
        ];
    }

    /**
     * A value the payload cannot read used to be sent as one centimetre, so the parcel was understated
     * tenfold and Yandex turned the shipment down on acceptance instead of at checkout.
     *
     * @param string $length Length as the shop typed it into the settings
     *
     * @dataProvider dpUnusableDimension
     */
    public function testAnUnusableDimensionStopsTheCalculation($length)
    {
        $this->expectException(Exception::class);

        $this->builder->buildPhysicalDimensions(
            ['weight' => 1],
            ['length' => $length, 'width' => 20, 'height' => 10]
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function dpUnusableDimension()
    {
        return [
            'not a number' => ['abc'],
            'empty'        => [''],
            'zero'         => ['0'],
        ];
    }

    /**
     * @param float|int|string $price    Price in rubles
     * @param int              $expected Expected price in kopecks
     *
     * @dataProvider dpPriceToMinorUnits
     */
    public function testPriceIsSentInKopecks($price, $expected)
    {
        $this->assertSame($expected, $this->builder->priceToMinorUnits($price));
    }

    /**
     * @return array<string, array{0: float|int|string, 1: int}>
     */
    public function dpPriceToMinorUnits()
    {
        return [
            'whole rubles'      => [100, 10000],
            'kopecks'           => [99.99, 9999],
            'numeric string'    => ['10.50', 1050],
            'part of a kopeck'  => [99.996, 10000],
            'free'              => [0, 0],
            'negative price'    => [-10, 0],
        ];
    }

    /**
     * @param string|null $delivery_type Delivery type from shipping settings
     * @param string      $expected      Expected tariff
     *
     * @dataProvider dpDeliveryType
     */
    public function testOnlyTariffsYandexKnowsAreSent($delivery_type, $expected)
    {
        $this->assertSame($expected, $this->builder->getDeliveryType(['delivery_type' => $delivery_type]));
    }

    /**
     * @return array<string, array{0: string|null, 1: string}>
     */
    public function dpDeliveryType()
    {
        return [
            'pickup point'    => [DeliveryType::SELF_PICKUP, DeliveryType::SELF_PICKUP],
            'courier'         => [DeliveryType::TIME_INTERVAL, DeliveryType::TIME_INTERVAL],
            'nothing chosen'  => ['', DeliveryType::TIME_INTERVAL],
            'unknown tariff'  => ['express', DeliveryType::TIME_INTERVAL],
        ];
    }

    public function testDropoffIsUsedUntilTheShopTurnsItOff()
    {
        $this->assertTrue($this->builder->isDropoffEnabled([]));
        $this->assertTrue($this->builder->isDropoffEnabled(['use_dropoff' => YesNo::YES]));
        $this->assertFalse($this->builder->isDropoffEnabled(['use_dropoff' => YesNo::NO]));
    }

    public function testSourceStationIsTakenFromTheFieldMatchingTheChosenScheme()
    {
        $service_params = [
            'dropoff_platform_station_id' => ' dropoff-1 ',
            'source_platform_station_id'  => 'warehouse-1',
        ];

        $this->assertSame('dropoff-1', $this->builder->getSourcePlatformStationId($service_params));

        $service_params['use_dropoff'] = YesNo::NO;
        $this->assertSame('warehouse-1', $this->builder->getSourcePlatformStationId($service_params));
    }

    public function testMissingStationIsReportedAgainstTheChosenScheme()
    {
        $this->assertSame(
            'yandex_delivery.dropoff_platform_station_id_required',
            $this->builder->getSourcePlatformStationRequiredMessage([])
        );
        $this->assertSame(
            'yandex_delivery.source_platform_station_id_required',
            $this->builder->getSourcePlatformStationRequiredMessage(['use_dropoff' => YesNo::NO])
        );
    }

    public function testAddressStartsWithThePostalCode()
    {
        // A trailing "19, 115477" reads as a coordinate pair and gets the address rejected.
        $address = $this->builder->buildAddress([
            'zipcode' => '115477',
            'city'    => 'Moscow',
            'address' => 'Kantemirovskaya st., 19',
            'state'   => 'Moscow',
            'country' => 'Russia',
        ]);

        $this->assertSame('115477, Moscow, Kantemirovskaya st., 19', $address);
    }

    public function testAddressFallsBackToTheRegionWhenNoStreetIsKnown()
    {
        $address = $this->builder->buildAddress(['state' => 'Moscow region', 'country' => 'Russia']);

        $this->assertSame('Moscow region, Russia', $address);
    }

    public function testAddressDoesNotRepeatTheSamePart()
    {
        $address = $this->builder->buildAddress(['city' => 'Moscow', 'address' => 'Moscow']);

        $this->assertSame('Moscow', $address);
    }

    public function testEmptyLocationGivesNoAddress()
    {
        $this->assertSame('', $this->builder->buildAddress(['city' => '', 'country' => null]));
    }

    public function testDimensionsComeFromTheParcelWhenItHasThem()
    {
        $package = [
            'weight'          => 2.5,
            'shipping_params' => ['box_length' => 30, 'box_width' => 20.5, 'box_height' => 10],
        ];
        $service_params = ['length' => 40, 'width' => 40, 'height' => 40];

        $this->assertSame(
            ['weight_gross' => 2500, 'dx' => 30, 'dy' => 20, 'dz' => 10],
            $this->builder->buildPhysicalDimensions($package, $service_params)
        );
    }

    public function testDimensionsFallBackToShippingSettings()
    {
        $package = ['weight' => 0.05, 'shipping_params' => ['box_length' => 30]];
        $service_params = ['length' => 40, 'width' => 25, 'height' => 15];

        $this->assertSame(
            ['weight_gross' => WeightConverter::MINIMUM_GRAMS, 'dx' => 30, 'dy' => 25, 'dz' => 15],
            $this->builder->buildPhysicalDimensions($package, $service_params)
        );
    }

    public function testParcelWithNoDimensionsAnywhereIsRefused()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('yandex_delivery.dimensions_required');

        $this->builder->buildPhysicalDimensions(['weight' => 1], []);
    }
}
