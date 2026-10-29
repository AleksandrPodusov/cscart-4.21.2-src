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

use Tygh\Addons\YandexDelivery\Services\PickupPointLimits;
use Tygh\Shippings\PickupPoints\Dto\PickupPoint;
use Tygh\Tests\Unit\ATestCase;

class PickupPointLimitsTest extends ATestCase
{
    /** @var PickupPointLimits */
    protected $limits;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->limits = new PickupPointLimits();
    }

    /**
     * @param string $name       Name Yandex sends for the point
     * @param bool   $is_postamat Whether the point is a parcel locker
     * @param int    $weight      Parcel weight in grams
     * @param array  $sides       Parcel sides in centimetres
     * @param string $state       Destination state code
     * @param bool   $expected    Whether the point survives the filter
     *
     * @dataProvider dpPointAndParcel
     */
    public function testOnlyThePointsThatCanTakeTheParcelSurvive(
        $name,
        $is_postamat,
        $weight,
        array $sides,
        $state,
        $expected
    ) {
        $result = $this->limits->apply(
            ['point' => $this->givenPoint($name, $is_postamat)],
            [$this->givenPlace($weight, $sides)],
            $state
        );

        $this->assertSame($expected, isset($result['points']['point']));
    }

    /**
     * Every boundary is taken from the Yandex table of weight and dimension limits and confirmed by a live
     * calculation: the value on the limit is priced, one unit above it is refused.
     *
     * @return array<string, array{0: string, 1: bool, 2: int, 3: array<int, int>, 4: string, 5: bool}>
     */
    public function dpPointAndParcel()
    {
        $market = 'Пункт выдачи заказов Яндекс Маркета';
        $five = '5 Post (Пятерочка)';
        $small = [10, 10, 10];

        return [
            'market takes 30 kg'              => [$market, false, 30000, $small, 'MOW', true],
            'market refuses 31 kg'            => [$market, false, 31000, $small, 'MOW', false],
            '5post takes 15 kg'               => [$five, false, 15000, $small, 'MOW', true],
            '5post refuses 16 kg'             => [$five, false, 16000, $small, 'MOW', false],
            'postamat takes 20 kg'            => [$market, true, 20000, $small, 'MOW', true],
            'postamat refuses 21 kg'          => [$market, true, 21000, $small, 'MOW', false],
            'market takes a 150 cm side'      => [$market, false, 1000, [150, 10, 10], 'MOW', true],
            'market refuses a 151 cm side'    => [$market, false, 1000, [151, 10, 10], 'MOW', false],
            'market refuses 330 cm of sides'  => [$market, false, 1000, [110, 110, 110], 'MOW', false],
            '5post refuses a 65 cm side'      => [$five, false, 1000, [65, 10, 10], 'MOW', false],
            '5post refuses 140 cm of sides'   => [$five, false, 1000, [48, 46, 46], 'MOW', false],
            'postamat refuses a 45 cm side'   => [$market, true, 1000, [45, 10, 10], 'MOW', false],
            // Murmansk is on the published list of regions with their own lower limits.
            'region caps market to 15 kg'     => [$market, false, 16000, $small, 'MUR', false],
            'region caps market to 60 cm'     => [$market, false, 1000, [61, 10, 10], 'MUR', false],
            'region leaves a small parcel be' => [$market, false, 15000, $small, 'MUR', true],
            // A name no marker matches must not silently hide the point.
            'unknown network is kept'         => ['Новая сеть', false, 30000, $small, 'MOW', true],
        ];
    }

    public function testPointsAreLeftAloneWhenTheParcelIsUnknown()
    {
        $points = ['a' => $this->givenPoint('5 Post (Пятерочка)', false)];

        $result = $this->limits->apply($points, [], 'MOW');

        $this->assertSame($points, $result['points']);
    }

    /**
     * A locker of a narrower network is bound by both sets at once, so the limits are laid over one another
     * rather than the first match winning. Nothing like it is on offer today, but the rule must not depend on that.
     *
     * @param int    $weight   Parcel weight in grams
     * @param array  $sides    Parcel sides in centimetres
     * @param bool   $expected Whether the point survives
     *
     * @dataProvider dpParcelForALockerOfANarrowNetwork
     */
    public function testALockerOfANarrowNetworkIsBoundByBothSets($weight, array $sides, $expected)
    {
        $result = $this->limits->apply(
            ['point' => $this->givenPoint('Постамат 5 Post', true)],
            [$this->givenPlace($weight, $sides)],
            'MOW'
        );

        $this->assertSame($expected, isset($result['points']['point']));
    }

    /**
     * @return array<string, array{0: int, 1: array<int, int>, 2: bool}>
     */
    public function dpParcelForALockerOfANarrowNetwork()
    {
        return [
            'within both sets'            => [15000, [10, 10, 10], true],
            // 16 kg passes the locker limit of 20 kg and fails the 5Post one of 15 kg.
            'past the network weight'     => [16000, [10, 10, 10], false],
            // A 45 cm side passes the 5Post limit of 64 cm and fails the locker one of 40 cm.
            'past the locker side'        => [1000, [45, 10, 10], false],
        ];
    }

    public function testTheOrderLimitStillCatchesWhatEachBoxPasses()
    {
        // Seven boxes of 30 kg each are fine one by one and 210 kg together, past the 200 kg order limit.
        $places = array_fill(0, 7, $this->givenPlace(30000, [10, 10, 10]));

        $result = $this->limits->apply(
            ['point' => $this->givenPoint('Пункт выдачи заказов Яндекс Маркета', false)],
            $places,
            'MOW'
        );

        $this->assertSame([], $result['points']);
        $this->assertSame(1, $result['dropped']['weight']);
    }

    public function testTheReasonIsCountedSoItCanBeLogged()
    {
        $result = $this->limits->apply(
            [
                'heavy' => $this->givenPoint('5 Post (Пятерочка)', false),
                'long'  => $this->givenPoint('5 Post (Пятерочка)', false),
            ],
            [$this->givenPlace(16000, [10, 10, 10])],
            'MOW'
        );

        $this->assertSame(['weight' => 2, 'side' => 0, 'sum' => 0], $result['dropped']);
    }

    /**
     * @param int    $weight   Parcel weight in grams
     * @param array  $sides    Parcel sides in centimetres
     * @param bool   $is_pickup Delivery to a pickup point
     * @param string $expected  Reason the method is withdrawn, empty string when it stays
     *
     * @dataProvider dpParcelBeyondEveryDelivery
     */
    public function testTheMethodIsWithdrawnOnlyWhenNoDeliveryTakesTheParcel(
        $weight,
        array $sides,
        $is_pickup,
        $expected
    ) {
        $this->assertSame(
            $expected,
            $this->limits->getUnsupportedParcelReason([$this->givenPlace($weight, $sides)], $is_pickup, 'MOW')
        );
    }

    /**
     * @return array<string, array{0: int, 1: array<int, int>, 2: bool, 3: string}>
     */
    public function dpParcelBeyondEveryDelivery()
    {
        $small = [10, 10, 10];

        return [
            'pickup keeps 30 kg'          => [30000, $small, true, ''],
            'pickup drops 31 kg'          => [31000, $small, true, 'weight'],
            'pickup keeps a 150 cm side'  => [1000, [150, 10, 10], true, ''],
            'pickup drops a 151 cm side'  => [1000, [151, 10, 10], true, 'side'],
            'pickup drops 330 cm of sides' => [1000, [110, 110, 110], true, 'sum'],
            // A courier carries what no pickup point would, so the method stays for door delivery.
            'door keeps 31 kg'            => [31000, $small, false, ''],
            'door keeps a 151 cm side'    => [1000, [151, 10, 10], false, ''],
            'door drops 201 kg'           => [201000, $small, false, 'weight'],
            'door drops a 301 cm side'    => [1000, [301, 10, 10], false, 'side'],
        ];
    }

    public function testSeveralBoxesAreWeighedPerBoxAndAgainstTheOrderLimit()
    {
        $two_boxes = [$this->givenPlace(20000, [10, 10, 10]), $this->givenPlace(20000, [10, 10, 10])];

        // Each box is under the 30 kg box limit and both together are under the 200 kg order limit.
        $this->assertSame('', $this->limits->getUnsupportedParcelReason($two_boxes, true, 'MOW'));

        $result = $this->limits->apply(
            ['point' => $this->givenPoint('Пункт выдачи заказов Яндекс Маркета', false)],
            $two_boxes,
            'MOW'
        );

        $this->assertArrayHasKey('point', $result['points']);
    }

    /**
     * The two checks must never disagree: a parcel the method is withdrawn for is a parcel every network
     * refuses, so the customer never meets an empty map, nor a hidden method with live points behind it.
     *
     * @param int   $weight Parcel weight in grams
     * @param array $sides  Parcel sides in centimetres
     *
     * @dataProvider dpParcelsAcrossTheRange
     */
    public function testWithdrawingTheMethodMatchesAnEmptyPointList($weight, array $sides)
    {
        $places = [$this->givenPlace($weight, $sides)];
        $points = [
            'market'   => $this->givenPoint('Пункт выдачи заказов Яндекс Маркета', false),
            'five'     => $this->givenPoint('5 Post (Пятерочка)', false),
            'postamat' => $this->givenPoint('Постамат', true),
        ];

        $withdrawn = $this->limits->getUnsupportedParcelReason($places, true, 'MOW') !== '';
        $no_points_left = $this->limits->apply($points, $places, 'MOW')['points'] === [];

        $this->assertSame($no_points_left, $withdrawn);
    }

    /**
     * @return array<string, array{0: int, 1: array<int, int>}>
     */
    public function dpParcelsAcrossTheRange()
    {
        return [
            'tiny'               => [1000, [10, 10, 10]],
            'at the 5post limit' => [15000, [10, 10, 10]],
            'past 5post'         => [16000, [10, 10, 10]],
            'at the market limit' => [30000, [150, 10, 10]],
            'past market weight' => [31000, [10, 10, 10]],
            'past market side'   => [1000, [151, 10, 10]],
            'past market sum'    => [1000, [110, 110, 110]],
        ];
    }

    /**
     * @param string $name        Point name
     * @param bool   $is_postamat Whether the point is a parcel locker
     *
     * @return PickupPoint
     */
    protected function givenPoint($name, $is_postamat)
    {
        return PickupPoint::create(
            'code-' . $name,
            'yandex_delivery',
            '213',
            'Москва, улица Мира, 1',
            37.6,
            55.7,
            [],
            $name,
            'Москва',
            null,
            '',
            '',
            '',
            $is_postamat
        );
    }

    /**
     * @param int             $weight Weight in grams
     * @param array<int, int> $sides  Sides in centimetres
     *
     * @return array{weight_gross: int, dx: int, dy: int, dz: int}
     */
    protected function givenPlace($weight, array $sides)
    {
        return [
            'weight_gross' => $weight,
            'dx'           => $sides[0],
            'dy'           => $sides[1],
            'dz'           => $sides[2],
        ];
    }
}
