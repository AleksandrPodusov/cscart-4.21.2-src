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

namespace Tygh\Addons\YandexDelivery\Tests\Unit\Shippings\Services;

use ReflectionMethod;
use Tygh\Shippings\Services\YandexDelivery;
use Tygh\Tests\Unit\ATestCase;

class YandexDeliveryRateCacheTest extends ATestCase
{
    /** @var YandexDelivery */
    protected $service;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new YandexDelivery();
    }

    /**
     * Builds a prepared request the way the service hands it to the calculator.
     *
     * @param string $point  Identifier of the pickup point
     * @param float  $weight Parcel weight
     *
     * @return array<string, string|array> Prepared request
     */
    protected function request($point, $weight = 1.0)
    {
        return [
            'url'     => 'https://b2b.taxi.yandex.net/pricing-calculator',
            'headers' => ['Authorization: Bearer token'],
            'data'    => json_encode([
                'destination' => ['platform_station' => ['platform_id' => $point]],
                'items'       => [['weight' => $weight]],
            ]),
        ];
    }

    /**
     * @param string $name Name of the method to reach
     * @param array  $args Arguments to pass
     *
     * @return mixed Whatever the method answered
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.DisallowMixedTypeHint.DisallowedMixedTypeHint
     */
    protected function call($name, array $args)
    {
        $method = new ReflectionMethod($this->service, $name);
        $method->setAccessible(true);

        return $method->invokeArgs($this->service, $args);
    }

    /**
     * Whatever point is priced, it is the same parcel, and its answers belong together.
     */
    public function testOneParcelStaysOneParcelWhateverPointIsPriced()
    {
        $this->assertSame(
            $this->call('getParcelHash', [$this->request('point-a')]),
            $this->call('getParcelHash', [$this->request('point-b')])
        );
    }

    /**
     * A changed cart must not be quoted the price of the old one.
     */
    public function testChangedParcelIsToldApart()
    {
        $this->assertNotSame(
            $this->call('getParcelHash', [$this->request('point-a', 1.0)]),
            $this->call('getParcelHash', [$this->request('point-a', 16.0)])
        );
    }

    /**
     * Answers of two points live side by side instead of overwriting each other.
     */
    public function testEachPointIsToldApartInsideTheParcel()
    {
        $this->assertNotSame(
            $this->call('getDestinationOfRequest', [$this->request('point-a')]),
            $this->call('getDestinationOfRequest', [$this->request('point-b')])
        );
        $this->assertSame(
            $this->call('getDestinationOfRequest', [$this->request('point-a', 1.0)]),
            $this->call('getDestinationOfRequest', [$this->request('point-a', 16.0)])
        );
    }

    /**
     * However much the shop is priced, the cache takes a fixed number of entries and reuses them.
     */
    public function testNumberOfEntriesDoesNotGrowWithTraffic()
    {
        $keys = [];

        for ($i = 0; $i < 5000; $i++) {
            $keys[$this->call('getSlotCacheKey', [$this->request('point-a', $i / 10)])] = true;
        }

        $this->assertLessThanOrEqual(YandexDelivery::RATE_CACHE_SLOTS, count($keys));
        $this->assertGreaterThan(1, count($keys), 'Parcels have to spread over the entries, not pile into one');
    }

    /**
     * An answer that outlived its time goes, and a parcel left with nothing goes with it.
     */
    public function testExpiredAnswersAreThrownOut()
    {
        $slot = [
            'parcel-fresh' => ['point-a' => ['body' => '{}', 'status' => 200, 'at' => 2000]],
            'parcel-stale' => ['point-a' => ['body' => '{}', 'status' => 200, 'at' => 500]],
            'parcel-mixed' => [
                'point-a' => ['body' => '{}', 'status' => 200, 'at' => 500],
                'point-b' => ['body' => '{}', 'status' => 200, 'at' => 2000],
            ],
        ];

        $kept = $this->call('dropExpiredAnswers', [$slot, 1000]);

        $this->assertSame(['parcel-fresh', 'parcel-mixed'], array_keys($kept));
        $this->assertSame(['point-b'], array_keys($kept['parcel-mixed']));
    }
}
