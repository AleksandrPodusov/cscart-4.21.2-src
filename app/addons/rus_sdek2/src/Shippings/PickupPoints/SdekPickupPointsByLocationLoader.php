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

namespace Tygh\Addons\RusSdek2\Shippings\PickupPoints;

use Tygh;
use Tygh\Addons\RusSdek2\Enum\DeliveryPointType;
use Tygh\Addons\RusSdek2\Shippings\SdekApiClient;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\YesNo;
use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Shippings\PickupPoints\Dto\PickupPoint;
use Tygh\Shippings\PickupPoints\Factories\ShippingInfoFactory;
use Tygh\Shippings\PickupPoints\LoaderByLocation\IPickupPointsLoaderByLocation;

class SdekPickupPointsByLocationLoader implements IPickupPointsLoaderByLocation
{
    /**
     * @param PickupPointsLookupContext $lookup_context Lookup context for pickup points search
     *
     * @return PickupPoint[]|array
     */
    public function getPickupPointsByLocation(PickupPointsLookupContext $lookup_context)
    {
        $pickup_points = [];

        /** @var \Tygh\Addons\RusSdek2\Services\CitiesService $cities_service */
        $cities_service = Tygh::$app['addons.rus_sdek2.cities_service'];
        $city_code = $cities_service->getCityId(
            [
                'city' => $lookup_context->getLocation()->getCity(),
                'country' => $lookup_context->getLocation()->getCountry(),
                'state' => $lookup_context->getLocation()->getState(),
                'zipcode' => $lookup_context->getLocation()->getZipcode(),
            ]
        );

        if (empty($city_code)) {
            return $pickup_points;
        }

        $shipping_info = ShippingInfoFactory::buildFromContext($lookup_context);

        $sdek_delivery = fn_get_schema('sdek', 'sdek_delivery', 'php', true);
        $tariff_code = $shipping_info['service_params']['tariff_code'] ?? null;

        if (empty($sdek_delivery[$tariff_code]['terminals']) || !YesNo::toBool($sdek_delivery[$tariff_code]['terminals'])) {
            return $pickup_points;
        }

        $params = [
            'city_code' => $city_code
        ];
        if (!empty($sdek_delivery[$tariff_code]['postamat'])) {
            $params['type'] = DeliveryPointType::POSTAMAT;
        } else {
            $params['type'] = DeliveryPointType::PVZ;
        }

        $module = db_get_field('SELECT module FROM ?:shipping_services WHERE service_id = ?i', $shipping_info['service_id']);

        /** @var \Tygh\Shippings\PickupPoints\Repository $pickup_points_repository */
        $pickup_points_repository = Tygh::$app['pickup_point.repository'];
        ksort($params);
        $cache_key = hash('sha256', implode(',', $params));

        if ($pickup_points_repository->checkPickupPointsSyncStateByCacheKey($module, $cache_key)) {
            $pickup_points = $pickup_points_repository->findPickupPointsByProviderAndCacheKey($module, $cache_key);
        }

        if (empty($pickup_points)) {
            try {
                $sdek_client = new SdekApiClient($shipping_info['service_params']);
                $cdek_delivery_points = $sdek_client->getDeliveryPoints($params);

                if ($cdek_delivery_points !== null) {
                    foreach ($cdek_delivery_points as $cdek_delivery_point) {
                        $pickup_point = PickupPoint::create(
                            $cdek_delivery_point['code'],
                            $module,
                            $city_code,
                            $cdek_delivery_point['location']['address'],
                            $cdek_delivery_point['location']['longitude'],
                            $cdek_delivery_point['location']['latitude'],
                            $cdek_delivery_point,
                            $cdek_delivery_point['name'],
                            $cdek_delivery_point['location']['city'] ?? null,
                            $cdek_delivery_point['nearest_station'] ?? null,
                            $cdek_delivery_point['work_time'] ?? null,
                            $cdek_delivery_point['phones'][0]['number'] ?? null,
                            $cdek_delivery_point['note'] ?? null,
                            $cdek_delivery_point['type'] === DeliveryPointType::POSTAMAT
                        );

                        $pickup_points[] = $pickup_point;
                    }
                }
            } catch (\Exception $e) {
                fn_set_notification(NotificationSeverity::ERROR, __('error'), $e->getMessage());
            }

            if (empty($pickup_points)) {
                return [];
            }

            $pickup_points_repository->setPickupPointsByCacheKey($pickup_points, $module, $cache_key);
        }

        if (!empty($pickup_points) && $params['type'] === DeliveryPointType::POSTAMAT) {
            /** @var \Tygh\Addons\RusSdek2\Services\SdekService $sdek_service */
            $sdek_service = Tygh::$app['addons.rus_sdek2.sdek_service'];

            $product_params = $sdek_service->prepareProductParamsByShippingInfo($shipping_info);
            $pickup_points = $sdek_service->filterPostamatPickupPoints($pickup_points, $product_params);
        }

        return $pickup_points;
    }
}
