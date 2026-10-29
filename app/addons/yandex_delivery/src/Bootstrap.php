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

namespace Tygh\Addons\YandexDelivery;

use Tygh\Core\ApplicationInterface;
use Tygh\Core\BootstrapInterface;
use Tygh\Core\HookHandlerProviderInterface;

/**
 * Loads the Yandex Delivery add-on.
 */
class Bootstrap implements BootstrapInterface, HookHandlerProviderInterface
{
    /**
     * @inheritDoc
     */
    public function boot(ApplicationInterface $app)
    {
        fn_define('YANDEX_DELIVERY_DEFAULT_WEIGHT_GRAMS', 100);

        $app->register(new ServiceProvider());
    }

    /**
     * @inheritDoc
     */
    public function getHookHandlerMap()
    {
        return [
            'update_cart_by_data_post' => [
                'addons.yandex_delivery.hook_handlers.carts',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\CartsHookHandler::onUpdateCartByDataPost() */
                'onUpdateCartByDataPost',
            ],
            'calculate_cart_taxes_pre' => [
                'addons.yandex_delivery.hook_handlers.carts',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\CartsHookHandler::onCalculateCartTaxesPre() */
                'onCalculateCartTaxesPre',
            ],
            'pre_place_order' => [
                'addons.yandex_delivery.hook_handlers.carts',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\CartsHookHandler::onPrePlaceOrder() */
                'onPrePlaceOrder',
            ],
            'place_suborders_pre' => [
                'addons.yandex_delivery.hook_handlers.carts',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\CartsHookHandler::onPlaceSubordersPre() */
                'onPlaceSubordersPre',
            ],
            'order_fulfillment_set_marketplace_shipping_to_product_group' => [
                'addons.yandex_delivery.hook_handlers.carts',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\CartsHookHandler::onOrderFulfillmentSetMarketplaceShippingToProductGroup() */
                'onOrderFulfillmentSetMarketplaceShippingToProductGroup',
            ],
            'pickup_point_variable_init' => [
                'addons.yandex_delivery.hook_handlers.pickup_points',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\PickupPointsHookHandler::onPickupPointVariableInit() */
                'onPickupPointVariableInit',
            ],
            'delete_shipments' => [
                'addons.yandex_delivery.hook_handlers.shipments',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\ShipmentsHookHandler::onDeleteShipments() */
                'onDeleteShipments',
            ],
            'get_shipments_info_post' => [
                'addons.yandex_delivery.hook_handlers.shipments',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\ShipmentsHookHandler::onGetShipmentsInfoPost() */
                'onGetShipmentsInfoPost',
            ],
            'update_shipping' => [
                'addons.yandex_delivery.hook_handlers.shippings',
                /** @see \Tygh\Addons\YandexDelivery\HookHandlers\ShippingsHookHandler::onUpdateShipping() */
                'onUpdateShipping',
            ],
        ];
    }
}
