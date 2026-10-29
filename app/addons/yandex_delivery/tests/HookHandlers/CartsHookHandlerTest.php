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

namespace Tygh\Addons\YandexDelivery\Tests\Unit\HookHandlers;

use Tygh\Addons\YandexDelivery\Enum\DeliveryType;
use Tygh\Addons\YandexDelivery\HookHandlers\CartsHookHandler;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Addons\YandexDelivery\Services\WeightConverter;
use Tygh\Tests\Unit\ATestCase;

class CartsHookHandlerTest extends ATestCase
{
    /** @var CartsHookHandler */
    protected $handler;

    /** @var array<string, mixed> */
    protected $request_backup = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->requireMockFunction('__');
        $this->requireMockFunction('fn_set_notification');

        $this->handler = new CartsHookHandler(new ShippingDataBuilder(new WeightConverter(1000)));
        $this->request_backup = $_REQUEST;
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $_REQUEST = $this->request_backup;

        parent::tearDown();
    }

    /**
     * @param string $chosen_in_cart    Pickup point kept in the cart, empty when none
     * @param string $posted_in_request Pickup point the storefront posts with the order form
     * @param string $refused           Pickup point Yandex refused to deliver to
     * @param bool   $expected          Whether the order may be placed
     *
     * @dataProvider dpPickupPointStates
     */
    public function testOrderIsRefusedForAPickupPointYandexTurnedDown(
        $chosen_in_cart,
        $posted_in_request,
        $refused,
        $expected
    ) {
        $cart = $this->givenCart($chosen_in_cart, $refused);
        $_REQUEST['select_pickup_point'] = $posted_in_request === '' ? [] : [0 => [13 => $posted_in_request]];
        $allow = true;

        $this->handler->onPrePlaceOrder($cart, $allow, $this->givenPickupGroups());

        $this->assertSame($expected, $allow);
    }

    /**
     * The storefront keeps the choice in its form, so it arrives with the order even after the calculation
     * dropped it from the cart — that is the case the refusal record exists for.
     *
     * @return array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    public function dpPickupPointStates()
    {
        return [
            'point works'                         => ['point-a', 'point-a', '', true],
            'point refused, still in the cart'     => ['point-a', 'point-a', 'point-a', false],
            'point refused, cart cleared, reposted' => ['', 'point-a', 'point-a', false],
            'another point was refused'            => ['point-b', 'point-b', 'point-a', true],
            'nothing chosen at all'                => ['', '', '', false],
            'cart cleared, nothing reposted'       => ['', '', 'point-a', false],
        ];
    }

    public function testDoorDeliveryNeedsNoPickupPoint()
    {
        $cart = $this->givenCart('', '');
        $_REQUEST['select_pickup_point'] = [];
        $allow = true;

        $groups = $this->givenPickupGroups();
        $groups[0]['chosen_shippings'][0]['service_params']['delivery_type'] = DeliveryType::TIME_INTERVAL;

        $this->handler->onPrePlaceOrder($cart, $allow, $groups);

        $this->assertTrue($allow);
    }

    /**
     * A calculation that did not take the point leaves the order waiting, however filled in the form is.
     */
    public function testOrderWaitsWhileTheCalculationTookNoPickupPoint()
    {
        $cart = $this->givenCart('', '');
        $cart['shippings_extra']['data'][0][13]['pickup_point_required'] = true;
        $_REQUEST['select_pickup_point'] = [0 => [13 => 'point-a']];
        $allow = true;

        $this->handler->onPrePlaceOrder($cart, $allow, $this->givenPickupGroups());

        $this->assertFalse($allow);
    }

    public function testCarriersOfOtherServicesAreLeftAlone()
    {
        $cart = $this->givenCart('', 'point-a');
        $_REQUEST['select_pickup_point'] = [];
        $allow = true;

        $groups = $this->givenPickupGroups();
        $groups[0]['chosen_shippings'][0]['module'] = 'rus_sdek2';

        $this->handler->onPrePlaceOrder($cart, $allow, $groups);

        $this->assertTrue($allow);
    }

    /**
     * Builds the cart the way the calculation leaves it.
     *
     * @param string $chosen  Pickup point kept in the cart, empty when none
     * @param string $refused Pickup point Yandex refused to deliver to
     *
     * @return array<string, array<int|string, array<int|string, mixed>>>
     */
    protected function givenCart($chosen, $refused)
    {
        $cart = ['select_pickup_point' => [], 'shippings_extra' => ['data' => [0 => [13 => []]]]];

        if ($chosen !== '') {
            $cart['select_pickup_point'] = [0 => [13 => $chosen]];
        }

        if ($refused !== '') {
            $cart['shippings_extra']['data'][0][13]['refused_pickup_point'] = $refused;
        }

        return $cart;
    }

    /**
     * Builds product groups with one Yandex Delivery pickup shipping chosen.
     *
     * @return array<int, array<string, array<int, array<string, mixed>>>>
     */
    protected function givenPickupGroups()
    {
        return [
            0 => [
                'chosen_shippings' => [
                    [
                        'shipping_id'    => 13,
                        'module'         => 'yandex_delivery',
                        'service_params' => ['delivery_type' => DeliveryType::SELF_PICKUP],
                    ],
                ],
            ],
        ];
    }
}
