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

use Tygh\Addons\YandexDelivery\Shippings\YandexDeliveryApiClient;
use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Enum\YesNo;
use Tygh\Registry;
use Exception;

/**
 * Explains why Yandex will not collect parcels from the warehouse chosen in the shipping method.
 *
 * Without a scheduled pickup Yandex prices nothing: every request answers `pickups_not_configured`. Measured on
 * the sandbox by booking a pickup and cancelling it again, the error appears and disappears with it.
 */
class PickupDiagnostics
{
    /** Yandex offers no pickup dates at all: the city is not served, or the address is outside the service area. */
    const VERDICT_UNAVAILABLE = 'yandex_delivery.pickup_unavailable';

    /** A pickup could be booked there, but none is scheduled, and without one nothing gets priced. */
    const VERDICT_NOT_SCHEDULED = 'yandex_delivery.pickup_not_scheduled';

    /** A missing pickup is a setup mistake rather than a per-minute event, so the storefront may reuse the answer. */
    const CACHE_TTL = 300;

    /**
     * Names the reason parcels will not be collected, or an empty string when there is nothing to report.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return string Language variable of the verdict
     */
    public function getVerdict(array $service_params)
    {
        $station_id = $this->getWarehouseId($service_params);

        if ($station_id === '') {
            return '';
        }

        $client = $this->createClient($service_params);

        try {
            $options = $client->getPickupOptions($station_id);
        } catch (Exception $e) {
            // Cities Yandex does not serve answer this call with an error rather than an empty list.
            return self::VERDICT_UNAVAILABLE;
        }

        if (ExternalValue::arr($options, 'pickup_options') === []) {
            return self::VERDICT_UNAVAILABLE;
        }

        // A failed call is not a diagnosis, and countScheduledPickups() reports it as null: staying silent
        // beats inventing a reason.
        return $this->countScheduledPickups($client, $station_id) === 0 ? self::VERDICT_NOT_SCHEDULED : '';
    }

    /**
     * Tells whether Yandex has a pickup scheduled for the warehouse, answering "yes" whenever it cannot be asked.
     *
     * The storefront drops the shipping method on a "no", so an unreachable API must not take a working method
     * off the checkout.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return bool
     */
    public function isPickupScheduled(array $service_params)
    {
        $station_id = $this->getWarehouseId($service_params);

        if ($station_id === '') {
            return true;
        }

        $cache_key = 'yandex_delivery_pickup_scheduled_' . sha1(implode('|', [
            ExternalValue::text($service_params, 'auth_token'),
            ExternalValue::text($service_params, 'test_mode'),
            $station_id,
        ]));

        return (bool) Registry::getOrSetCache(
            $cache_key,
            [
                'update_handlers' => [],
                'ttl' => self::CACHE_TTL,
            ],
            'static',
            function () use ($service_params, $station_id) {
                $count = $this->countScheduledPickups($this->createClient($service_params), $station_id);

                return $count === null || $count > 0;
            }
        );
    }

    /**
     * Gets the warehouse a pickup would be booked at; the drop-off scheme needs none.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return string
     */
    protected function getWarehouseId(array $service_params)
    {
        if (YesNo::toBool(ExternalValue::text($service_params, 'use_dropoff') ?: YesNo::YES)) {
            return '';
        }

        return trim(ExternalValue::text($service_params, 'source_platform_station_id'));
    }

    /**
     * Counts pickups that are still going to happen, or reports that Yandex could not be asked.
     *
     * Cancelled pickups drop out of the list entirely, but the status is checked anyway: a list that starts
     * carrying them would otherwise read as a working setup.
     *
     * @param YandexDeliveryApiClient $client     API client
     * @param string                  $station_id Warehouse station ID
     *
     * @return int|null Number of scheduled pickups, or null when the request failed
     */
    protected function countScheduledPickups(YandexDeliveryApiClient $client, $station_id)
    {
        try {
            $response = $client->getScheduledPickups($station_id);
        } catch (Exception $e) {
            return null;
        }

        $count = 0;

        foreach (ExternalValue::arr($response, 'pickups') as $pickup) {
            if (is_array($pickup) && ExternalValue::text($pickup, 'pickup_status') === 'scheduled') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Builds an API client from the credentials of the shipping method.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return YandexDeliveryApiClient
     */
    protected function createClient(array $service_params)
    {
        return new YandexDeliveryApiClient([
            'auth_token' => ExternalValue::text($service_params, 'auth_token'),
            'test_mode'  => ExternalValue::text($service_params, 'test_mode'),
        ]);
    }
}
