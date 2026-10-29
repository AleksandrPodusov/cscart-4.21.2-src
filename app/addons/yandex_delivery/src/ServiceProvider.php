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

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Tygh\Addons\YandexDelivery\HookHandlers\CartsHookHandler;
use Tygh\Addons\YandexDelivery\HookHandlers\PickupPointsHookHandler;
use Tygh\Addons\YandexDelivery\HookHandlers\ShipmentsHookHandler;
use Tygh\Addons\YandexDelivery\HookHandlers\ShippingsHookHandler;
use Tygh\Addons\YandexDelivery\Repositories\RequestRepository;
use Tygh\Addons\YandexDelivery\Services\ConnectionChecker;
use Tygh\Addons\YandexDelivery\Services\OrderTaxRates;
use Tygh\Addons\YandexDelivery\Services\PickupDiagnostics;
use Tygh\Addons\YandexDelivery\Services\PickupPointLimits;
use Tygh\Addons\YandexDelivery\Services\RequestDataBuilder;
use Tygh\Addons\YandexDelivery\Services\RequestService;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Addons\YandexDelivery\Services\WeightConverter;
use Tygh\Addons\YandexDelivery\Services\WarehousePickerService;
use Tygh\Application;
use Tygh\Enum\ObjectStatuses;
use Tygh\Registry;

/**
 * Registers add-on services.
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritDoc
     *
     * @return void
     */
    public function register(Container $app)
    {
        // The store keeps weight in its own unit; the factor is read here so the payload code stays unaware of it.
        $app['addons.yandex_delivery.weight_converter'] = static function () {
            $minimum_grams = defined('YANDEX_DELIVERY_DEFAULT_WEIGHT_GRAMS')
                ? YANDEX_DELIVERY_DEFAULT_WEIGHT_GRAMS
                : WeightConverter::MINIMUM_GRAMS;

            return new WeightConverter(Registry::get('settings.General.weight_symbol_grams'), $minimum_grams);
        };

        $app['addons.yandex_delivery.shipping_data_builder'] = static function (Application $app) {
            return new ShippingDataBuilder($app['addons.yandex_delivery.weight_converter']);
        };

        $app['addons.yandex_delivery.hook_handlers.carts'] = static function (Application $app) {
            return new CartsHookHandler($app['addons.yandex_delivery.shipping_data_builder']);
        };

        $app['addons.yandex_delivery.hook_handlers.pickup_points'] = static function () {
            return new PickupPointsHookHandler();
        };

        $app['addons.yandex_delivery.request_repository'] = static function () {
            return new RequestRepository();
        };

        // The taxes add-on is optional: its classes only autoload while it is active, so the factory is
        // resolved here and the service is handed nothing when the add-on is off.
        $app['addons.yandex_delivery.order_tax_rates'] = static function (Application $app) {
            $is_available = Registry::get('addons.rus_taxes.status') === ObjectStatuses::ACTIVE
                && isset($app['addons.rus_taxes.receipt_factory']);

            return new OrderTaxRates($is_available ? $app['addons.rus_taxes.receipt_factory'] : null);
        };

        $app['addons.yandex_delivery.request_data_builder'] = static function (Application $app) {
            return new RequestDataBuilder(
                $app['addons.yandex_delivery.order_tax_rates'],
                $app['addons.yandex_delivery.shipping_data_builder']
            );
        };

        $app['addons.yandex_delivery.request_service'] = static function (Application $app) {
            return new RequestService(
                $app['addons.yandex_delivery.request_repository'],
                $app['addons.yandex_delivery.request_data_builder']
            );
        };

        $app['addons.yandex_delivery.warehouse_picker_service'] = static function () {
            return new WarehousePickerService();
        };

        $app['addons.yandex_delivery.connection_checker'] = static function () {
            return new ConnectionChecker();
        };

        $app['addons.yandex_delivery.pickup_diagnostics'] = static function () {
            return new PickupDiagnostics();
        };

        $app['addons.yandex_delivery.pickup_point_limits'] = static function () {
            return new PickupPointLimits();
        };

        $app['addons.yandex_delivery.hook_handlers.shippings'] = static function (Application $app) {
            return new ShippingsHookHandler(
                $app['addons.yandex_delivery.pickup_diagnostics'],
                $app['addons.yandex_delivery.shipping_data_builder']
            );
        };

        $app['addons.yandex_delivery.hook_handlers.shipments'] = static function (Application $app) {
            return new ShipmentsHookHandler(
                $app['addons.yandex_delivery.request_repository'],
                $app['addons.yandex_delivery.request_service']
            );
        };
    }
}
