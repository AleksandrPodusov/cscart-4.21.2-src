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

namespace Tygh\Addons\YandexDelivery\Shippings\PickupPoints;

use Tygh;
use Tygh\Addons\YandexDelivery\Shippings\YandexDeliveryApiClient;
use Tygh\Addons\YandexDelivery\Enum\DeliveryType;
use Tygh\Addons\YandexDelivery\Enum\PickupStationType;
use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Addons\YandexDelivery\Services\PickupDiagnostics;
use Tygh\Addons\YandexDelivery\Services\PickupPointLimits;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Shippings\PickupPoints\Dto\PickupPoint;
use Tygh\Shippings\PickupPoints\Factories\ShippingInfoFactory;
use Tygh\Shippings\PickupPoints\LoaderByLocation\IPickupPointsLoaderByLocation;
use Tygh\Registry;
use Exception;

/**
 * Loads Yandex Delivery pickup points by customer location.
 */
class YandexDeliveryPickupPointsByLocationLoader implements IPickupPointsLoaderByLocation
{
    /** Points converted and written before the next portion is read. Kept small: this is what memory costs. */
    const STORE_BATCH_SIZE = 50;

    /** @var ShippingDataBuilder */
    protected $shipping_data_builder;

    /** @var PickupDiagnostics */
    protected $pickup_diagnostics;

    /** @var PickupPointLimits */
    protected $pickup_point_limits;

    /**
     * @param ShippingDataBuilder $shipping_data_builder Reads the shipping method setup
     * @param PickupDiagnostics   $pickup_diagnostics    Tells whether Yandex will collect parcels at all
     * @param PickupPointLimits   $pickup_point_limits   Knows which points will not take the parcel
     *
     * @return void
     */
    public function __construct(
        ShippingDataBuilder $shipping_data_builder,
        PickupDiagnostics $pickup_diagnostics,
        PickupPointLimits $pickup_point_limits
    ) {
        $this->shipping_data_builder = $shipping_data_builder;
        $this->pickup_diagnostics = $pickup_diagnostics;
        $this->pickup_point_limits = $pickup_point_limits;
    }

    /**
     * @param PickupPointsLookupContext $lookup_context Pickup points lookup context
     *
     * @return array<string, PickupPoint>
     */
    public function getPickupPointsByLocation(PickupPointsLookupContext $lookup_context)
    {
        $pickup_points = [];
        $shipping_info = ShippingInfoFactory::buildFromContext($lookup_context);
        $service_params = $shipping_info['service_params'] ?? [];

        if (!DeliveryType::isPickup($this->shipping_data_builder->getDeliveryType($service_params))) {
            return $pickup_points;
        }

        if (empty($service_params['auth_token'])) {
            return $pickup_points;
        }

        // Without a scheduled pickup Yandex prices nothing, so offering points would only let the customer
        // choose one and watch the shipping method fall away afterwards.
        if (!$this->pickup_diagnostics->isPickupScheduled($service_params)) {
            return $pickup_points;
        }

        $location = $lookup_context->getLocation()->toArray();
        $location_string = $this->shipping_data_builder->buildAddress($location);

        if ($location_string === '') {
            return $pickup_points;
        }

        $module = db_get_field('SELECT module FROM ?:shipping_services WHERE service_id = ?i', $shipping_info['service_id']);
        $module = $module ?: 'yandex_delivery';
        $client = new YandexDeliveryApiClient($service_params);

        try {
            $geo_id = $this->getGeoId($client, $service_params, $location_string);
        } catch (Exception $e) {
            $this->logLoadFailure($client, $e->getMessage());

            return $pickup_points;
        }

        if ($geo_id === 0) {
            return $pickup_points;
        }

        $cache_params = [
            'auth_token_hash' => sha1((string) $service_params['auth_token']),
            'cache_scheme_version' => '4',
            'geo_id' => (string) $geo_id,
            // The stored schedule is localized, so two languages must not share one cache entry.
            'lang_code' => defined('CART_LANGUAGE') ? CART_LANGUAGE : '',
            'test_mode' => $service_params['test_mode'] ?? '',
            'types' => PickupStationType::PICKUP_POINT . ',' . PickupStationType::TERMINAL,
        ];

        /** @var \Tygh\Shippings\PickupPoints\Repository $pickup_points_repository */
        $pickup_points_repository = Tygh::$app['pickup_point.repository'];
        ksort($cache_params);
        $cache_key = hash('sha256', implode(',', $cache_params));

        if ($pickup_points_repository->checkPickupPointsSyncStateByCacheKey($module, $cache_key)) {
            $pickup_points = $pickup_points_repository->findPickupPointsByProviderAndCacheKey(
                $module,
                $cache_key,
                $lookup_context->isExtraDataRequired()
            );
        }

        if (!empty($pickup_points)) {
            return $this->applyParcelLimits($pickup_points, $shipping_info);
        }

        $stored = 0;

        try {
            // Yandex is asked for the points that take a prepaid parcel: the shop is always paid at checkout.
            $payment_method = ShippingDataBuilder::PAYMENT_METHOD_ALREADY_PAID;
            foreach ([PickupStationType::PICKUP_POINT, PickupStationType::TERMINAL] as $type) {
                // The response is not put into a variable of its own: a second owner of the data would keep every
                // station of the city alive until the end of the loop.
                $stations = ExternalValue::arr($client->getPickupPoints([
                    'geo_id' => $geo_id,
                    'type' => $type,
                    'payment_method' => $payment_method,
                    'payment_methods' => [$payment_method],
                ]), 'points');

                $batch = [];

                while ($stations !== []) {
                    $station = array_pop($stations);
                    $pickup_point = is_array($station)
                        ? $this->createPickupPoint($station, $module, (string) $geo_id)
                        : null;

                    if ($pickup_point !== null) {
                        $batch[$pickup_point->getPickupPointCode()] = $pickup_point;
                    }

                    if (count($batch) < self::STORE_BATCH_SIZE) {
                        continue;
                    }

                    $pickup_points_repository->storePickupPoints($batch, $cache_key);
                    $stored += count($batch);
                    $batch = [];
                }

                $pickup_points_repository->storePickupPoints($batch, $cache_key);
                $stored += count($batch);
            }
        } catch (Exception $e) {
            // Whatever reached the table stays unmarked, so the next request loads the city again.
            $this->logLoadFailure($client, $e->getMessage());

            return [];
        }

        if ($stored === 0) {
            return [];
        }

        // The city is complete only now, and only now may it be read from the cache.
        $pickup_points_repository->setPickupPointSyncState($module, $cache_key, time());

        return $this->applyParcelLimits(
            $pickup_points_repository->findPickupPointsByProviderAndCacheKey(
                $module,
                $cache_key,
                $lookup_context->isExtraDataRequired()
            ),
            $shipping_info
        );
    }

    /**
     * Hides the points that will not take this parcel.
     *
     * Applied after the cache, never before it: the stored list belongs to the whole city and must stay complete
     * for every other cart.
     *
     * @param array<string, PickupPoint>                      $pickup_points Points the city list holds
     * @param array<string, string|int|float|bool|array|null> $shipping_info Shipping data with the package info
     *
     * @return array<string, PickupPoint>
     */
    protected function applyParcelLimits(array $pickup_points, array $shipping_info)
    {
        if ($pickup_points === []) {
            return $pickup_points;
        }

        $package_info = $shipping_info['package_info'] ?? [];
        $places = [];

        try {
            foreach ($package_info['packages'] ?? [] as $package) {
                $place = $this->shipping_data_builder->buildPhysicalDimensions(
                    (array) $package,
                    (array) ($shipping_info['service_params'] ?? [])
                );

                // The lookup context hands the weight over already converted to grams, unlike a cart package,
                // so the builder's own conversion would apply the store factor a second time.
                $place['weight_gross'] = (int) ($package['weight'] ?? 0);
                $places[] = $place;
            }
        } catch (Exception $e) {
            // Without dimensions there is nothing to compare, and guessing them would hide working points.
            return $pickup_points;
        }

        $result = $this->pickup_point_limits->apply(
            $pickup_points,
            $places,
            (string) (($package_info['location']['state'] ?? '') ?: '')
        );

        $this->logHiddenPoints($result['dropped'], count($pickup_points));

        return $result['points'];
    }

    /**
     * Writes into the shipping log how many points were hidden and what about the parcel they refused.
     *
     * @param array<string, int> $dropped Points hidden, by reason
     * @param int                $total   Points the city list held
     *
     * @return void
     */
    protected function logHiddenPoints(array $dropped, $total)
    {
        $hidden = array_sum($dropped);

        if ($hidden === 0) {
            return;
        }

        $message = sprintf(
            'Pickup points hidden by parcel limits: %d of %d (weight %d, longest side %d, sum of sides %d)',
            $hidden,
            $total,
            $dropped['weight'],
            $dropped['side'],
            $dropped['sum']
        );

        fn_log_event('requests', 'shipping', [
            'url'      => '',
            'response' => $message,
            'data'     => var_export($dropped, true),
            'shipping' => 'yandex_delivery',
        ]);
    }

    /**
     * Resolves an address into the region Yandex lists its pickup points by.
     *
     * Cached because the answer for an address never changes, while asking for it is a request of its own — and
     * the points cache is keyed by the answer, so the question now comes up on every checkout, not only on a miss.
     *
     * @param YandexDeliveryApiClient                   $client          API client
     * @param array<string, string|int|float|bool|null> $service_params  Shipping service parameters
     * @param string                                    $location_string Customer address
     *
     * @return int Region identifier, zero when the address could not be resolved
     *
     * @throws \Exception When the request to Yandex fails.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getGeoId(YandexDeliveryApiClient $client, array $service_params, $location_string)
    {
        $cache_key = 'yandex_delivery_geo_id_' . sha1(implode('|', [
            ExternalValue::text($service_params, 'auth_token'),
            ExternalValue::text($service_params, 'test_mode'),
            $location_string,
        ]));

        return (int) Registry::getOrSetCache(
            $cache_key,
            [
                'update_handlers' => [],
                'ttl' => SECONDS_IN_DAY,
            ],
            'static',
            static function () use ($client, $location_string) {
                return (int) $client->detectGeoId($location_string);
            }
        );
    }

    /**
     * Writes a failed pickup point load into the shipping request log.
     *
     * @param YandexDeliveryApiClient|null $client  API client the failure came from
     * @param string                       $message Failure message
     *
     * @return void
     */
    protected function logLoadFailure($client, $message)
    {
        if ($message === '') {
            return;
        }

        fn_log_event(
            'requests',
            'shipping',
            [
                'url'      => $client === null ? '' : $client->getEndpoint('/pickup-points/list'),
                'response' => $message,
                'data'     => var_export(['error' => $message], true),
                'shipping' => 'yandex_delivery',
            ]
        );
    }


    /**
     * Removes characters outside the Basic Multilingual Plane, emoji above all.
     *
     * Yandex puts them into the pickup point instruction, and the cache columns are three-byte utf8: MySQL cuts
     * the value at the first such character, which leaves the whole serialized station unreadable.
     *
     * @param array<string, string|int|float|bool|array|object|null> $station Station data
     *
     * @return array<string, string|int|float|bool|array|object|null>
     */
    protected function stripSupplementaryCharacters(array $station)
    {
        foreach ($station as $key => $value) {
            if (is_array($value)) {
                $station[$key] = $this->stripSupplementaryCharacters($value);
            } elseif (is_string($value)) {
                $station[$key] = preg_replace('/[\xF0-\xF4][\x80-\xBF]{3}/', '', $value);
            }
        }

        return $station;
    }

    /**
     * Creates a pickup point DTO from a Yandex station.
     *
     * @param array<string, string|int|float|bool|array|object|null> $station            Station data
     * @param string                                                 $module             Shipping service module
     * @param string                                                 $provider_city_code Yandex geo_id
     *
     * @return PickupPoint|null
     */
    protected function createPickupPoint(array $station, $module, $provider_city_code)
    {
        $station = $this->stripSupplementaryCharacters($station);

        $pickup_point_code = ExternalValue::text($station, 'id') ?: ExternalValue::text($station, 'platform_station_id');
        if ($pickup_point_code === '') {
            return null;
        }

        $address = $this->getStationAddress($station);
        if ($address === '') {
            return null;
        }

        $position = ExternalValue::arr($station, 'position');
        $station_address = ExternalValue::arr($station, 'address');
        $latitude = ExternalValue::float($position, 'latitude');
        $longitude = ExternalValue::float($position, 'longitude');
        $work_time = $this->formatSchedule(ExternalValue::arr($station, 'schedule'));
        $phone = ExternalValue::text(ExternalValue::arr($station, 'contact'), 'phone');
        $notes = ExternalValue::text($station, 'instruction') ?: ExternalValue::text($station_address, 'comment');
        $city = ExternalValue::text($station_address, 'locality');
        $type = ExternalValue::text($station, 'type');

        $station['code'] = $pickup_point_code;
        $station['platform_station_id'] = $pickup_point_code;
        $station['work_time'] = $work_time;
        $station['location'] = [
            'address' => $address,
            'address_full' => $address,
            'city' => $city,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
        $station['address_comment'] = $notes;

        return PickupPoint::create(
            $pickup_point_code,
            $module,
            $provider_city_code,
            $address,
            $longitude,
            $latitude,
            $station,
            ExternalValue::text($station, 'name'),
            $city,
            null,
            $work_time,
            $phone,
            $notes,
            $type === PickupStationType::TERMINAL
        );
    }

    /**
     * Gets formatted station address.
     *
     * @param array<string, string|int|float|bool|array|object|null> $station Station data
     *
     * @return string
     */
    protected function getStationAddress(array $station)
    {
        $address = $station['address'] ?? [];
        if (!is_array($address)) {
            return '';
        }

        if (!empty($address['full_address'])) {
            return $this->normalizeAddressValue($address['full_address']);
        }

        return '';
    }

    /**
     * Normalizes scalar address value.
     *
     * @param string|int|float|bool|array<int|string, string|int|float|bool|null>|object|null $value Address value
     *
     * @return string
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function normalizeAddressValue($value)
    {
        if ($value === null || $value === false || is_array($value) || is_object($value)) {
            return '';
        }

        $value = trim((string) $value);
        if ($value === '' || in_array(strtolower($value), ['false', 'null'], true)) {
            return '';
        }

        return (string) preg_replace('/\s+/u', ' ', $value);
    }

    /**
     * Formats a pickup point schedule into a readable line.
     *
     * @param array{time_zone?: int|string, restrictions?: array<int, array<string, string|int|array|null>>} $schedule Schedule data
     *
     * @return string
     */
    protected function formatSchedule(array $schedule)
    {
        if (empty($schedule['restrictions']) || !is_array($schedule['restrictions'])) {
            return '';
        }

        // Yandex sends one restriction per weekday, so days sharing the same intervals are grouped back:
        // seven repetitions of one line, or a day repeated for every lunch break, are unreadable.
        $times_by_day = [];
        $times_without_day = [];

        foreach ($schedule['restrictions'] as $restriction) {
            if (!is_array($restriction)) {
                continue;
            }

            $from = $this->formatTime($restriction['time_from'] ?? []);
            $to = $this->formatTime($restriction['time_to'] ?? []);
            $time = $from === '' || $to === '' ? $from . $to : $from . '–' . $to;

            if ($time === '') {
                continue;
            }

            $days = array_map('intval', (array) ($restriction['days'] ?? []));
            if (empty($days)) {
                $times_without_day[] = $time;
                continue;
            }

            foreach ($days as $day) {
                $times_by_day[$day][] = $time;
            }
        }

        $days_by_times = [];
        foreach ($times_by_day as $day => $times) {
            $times = array_unique($times);
            sort($times);
            $days_by_times[implode(', ', $times)][] = $day;
        }

        $parts = [];
        foreach ($days_by_times as $times => $days) {
            $days_label = $this->formatDays($days);
            $parts[] = $days_label === '' ? $times : $days_label . ': ' . $times;
        }

        foreach (array_unique($times_without_day) as $time) {
            $parts[] = $time;
        }

        if (empty($parts)) {
            return '';
        }

        return implode('; ', $parts) . $this->formatTimeZone($schedule['time_zone'] ?? null);
    }

    /**
     * Names the time zone, but only when it differs from the store one.
     *
     * Opening hours belong to the place, so they are never converted: what is shown must match the sign on the door.
     *
     * @param int|string|null $time_zone UTC offset in hours as sent by Yandex
     *
     * @return string
     */
    protected function formatTimeZone($time_zone)
    {
        if (!is_numeric($time_zone)) {
            return '';
        }

        $point_offset = (int) $time_zone;
        $store_offset = (int) round((int) date('Z') / 3600);

        if ($point_offset === $store_offset) {
            return '';
        }

        return ' (' . __('yandex_delivery.schedule.utc_offset', ['[offset]' => sprintf('%+d', $point_offset)]) . ')';
    }

    /**
     * Formats a weekday list, collapsing runs into ranges.
     *
     * @param int[] $days Yandex weekday numbers, 1 for Monday through 7 for Sunday
     *
     * @return string
     */
    protected function formatDays(array $days)
    {
        $days = array_values(array_unique(array_filter($days, static function ($day) {
            return $day >= 1 && $day <= 7;
        })));
        sort($days);

        if (empty($days)) {
            return '';
        }

        if (count($days) === 7) {
            return __('yandex_delivery.schedule.daily');
        }

        $parts = [];
        foreach ($this->groupConsecutive($days) as $run) {
            $parts[] = count($run) >= 3
                ? $this->getDayName($run[0]) . '–' . $this->getDayName(end($run))
                : implode(', ', array_map([$this, 'getDayName'], $run));
        }

        return implode(', ', $parts);
    }

    /**
     * Splits a sorted list of numbers into runs of consecutive values.
     *
     * @param int[] $numbers Sorted numbers
     *
     * @return array<int, int[]>
     */
    protected function groupConsecutive(array $numbers)
    {
        $runs = [];
        foreach ($numbers as $number) {
            $last_run = count($runs) - 1;
            if ($last_run >= 0 && end($runs[$last_run]) === $number - 1) {
                $runs[$last_run][] = $number;
                continue;
            }

            $runs[] = [$number];
        }

        return $runs;
    }

    /**
     * Gets a localized short weekday name.
     *
     * Yandex counts Monday as 1 and Sunday as 7, the core language variables count Sunday as 0.
     *
     * @param int $day Yandex weekday number
     *
     * @return string
     */
    protected function getDayName($day)
    {
        return __('weekday_abr_' . ($day % 7));
    }

    /**
     * Formats time.
     *
     * @param array{hours?: int|string, minutes?: int|string}|null $time Time data
     *
     * @return string
     */
    protected function formatTime($time)
    {
        if (!is_array($time) || !isset($time['hours'])) {
            return '';
        }

        return sprintf('%02d:%02d', (int) $time['hours'], (int) ($time['minutes'] ?? 0));
    }
}
