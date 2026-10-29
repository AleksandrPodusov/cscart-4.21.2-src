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

namespace Tygh\Shippings\PickupPoints\LoaderByLocation;

use Tygh;
use Tygh\Shippings\IPickupPointsService;

class ShippingMethodProvidedPickupPointsLoaderFactory
{
    /**
     * @param int $shipping_id Shipping identifier
     *
     * @return IPickupPointsLoaderByLocation|null
     */
    public function getShippingMethodProvidedPickupPointsLoader($shipping_id)
    {
        /** @var \Tygh\Shippings\PickupPoints\Repository $pickup_points_repository */
        $pickup_points_repository = Tygh::$app['pickup_point.repository'];
        $shipping_info = $pickup_points_repository->getShippingInfoForPickupPoints($shipping_id);

        $module = db_get_field(
            'SELECT module FROM ?:shipping_services WHERE service_id = ?i',
            $shipping_info['service_id']
        );

        $module_camelized = fn_camelize($module);
        $shipping_service_class = 'Tygh\\Shippings\\Services\\' . $module_camelized;

        if (!class_exists($shipping_service_class)) {
            return null;
        }

        /** @var \Tygh\Shippings\IPickupPointsService $shipping_service Shipping service */
        $shipping_service = new $shipping_service_class();
        if ($shipping_service instanceof IPickupPointsService) {
            return $shipping_service->getPickupPointsLoader();
        }

        return null;
    }
}
