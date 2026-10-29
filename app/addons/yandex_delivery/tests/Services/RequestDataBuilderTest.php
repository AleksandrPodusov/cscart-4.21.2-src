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

use Tygh\Addons\YandexDelivery\Services\OrderTaxRates;
use Tygh\Addons\YandexDelivery\Services\RequestDataBuilder;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Addons\YandexDelivery\Services\WeightConverter;
use Tygh\Tests\Unit\ATestCase;

class RequestDataBuilderTest extends ATestCase
{
    /** @var RequestDataBuilder */
    protected $builder;

    /** @var array<string, int> */
    protected $order = ['order_id' => 123];

    /** @var array<string, int> */
    protected $shipment = ['shipment_id' => 45];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new RequestDataBuilder(
            new OrderTaxRates(),
            new ShippingDataBuilder(new WeightConverter(1000))
        );
    }

    public function testShipmentIsIdentifiedByOrderAndShipment()
    {
        $this->assertSame('yd-123-45', $this->builder->getOperatorRequestId($this->order, $this->shipment));
    }

    public function testIdentifierStaysTheSameOnEveryAttempt()
    {
        // Yandex answers a repeat with `208` and the request_id it already holds, so keeping the identifier is
        // what stops a lost answer from turning into a second parcel.
        $this->assertSame('yd-123-45', $this->builder->getOperatorRequestId($this->order, $this->shipment));
        $this->assertSame('yd-123-45', $this->builder->getOperatorRequestId($this->order, $this->shipment));
    }
}
