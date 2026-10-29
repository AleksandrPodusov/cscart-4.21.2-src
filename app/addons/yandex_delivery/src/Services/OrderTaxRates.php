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

use Tygh\Addons\RusTaxes\Receipt\Item as ReceiptItem;
use Exception;

/**
 * Resolves the VAT rate Yandex expects for every product of an order.
 *
 * The rate lives in the special add-on for taxes, which this module does not depend on. When that add-on is off the
 * receipt factory is absent, and every item falls back to "no VAT" instead of the module refusing to ship.
 */
class OrderTaxRates
{
    /** Value Yandex expects when an order carries no VAT. */
    const NDS_NONE = -1;

    /**
     * @var \Tygh\Addons\RusTaxes\ReceiptFactory|null Builds the fiscal view of an order, absent without the taxes add-on
     */
    protected $receipt_factory;

    /**
     * @param \Tygh\Addons\RusTaxes\ReceiptFactory|null $receipt_factory Receipt factory, or null when unavailable
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function __construct($receipt_factory = null)
    {
        $this->receipt_factory = $receipt_factory;
    }

    /**
     * Builds the VAT rate for every product of an order, keyed the same way as the order products are.
     *
     * @param array<string, string|int|float|array|null> $order_info Order data
     *
     * @return array<int|string, int> Cart ID as key, VAT rate as value; empty when no rate can be resolved
     */
    public function getByCartId(array $order_info)
    {
        $receipt = $this->createReceipt($order_info);
        if ($receipt === null) {
            return [];
        }

        $map = fn_get_schema('yandex_delivery', 'map_taxes');
        $rates = [];

        foreach ($receipt->getItems() as $item) {
            if ($item->getType() !== ReceiptItem::TYPE_PRODUCT) {
                continue;
            }

            $tax_type = $item->getTaxType();
            $rates[$item->getId()] = isset($map[$tax_type]) ? (int) $map[$tax_type] : self::NDS_NONE;
        }

        return $rates;
    }

    /**
     * Builds the fiscal view of the order, which also spreads the order-wide discount over the items.
     *
     * @param array<string, string|int|float|array|null> $order_info Order data
     *
     * @return \Tygh\Addons\RusTaxes\Receipt\Receipt|null
     */
    protected function createReceipt(array $order_info)
    {
        if ($this->receipt_factory === null) {
            return null;
        }

        try {
            return $this->receipt_factory->createReceiptFromOrder(
                $order_info,
                CART_PRIMARY_CURRENCY,
                true,
                [ReceiptItem::TYPE_PRODUCT]
            );
        } catch (Exception $e) {
            return null;
        }
    }
}
