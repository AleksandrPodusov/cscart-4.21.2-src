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

use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Addons\YandexDelivery\Enum\PickupStationType;
use Tygh\Addons\YandexDelivery\Shippings\YandexDeliveryApiClient;
use Tygh\Registry;
use Exception;

/**
 * Provides Yandex Delivery warehouse data for backend object pickers.
 */
class WarehousePickerService
{
    /** Shorter input geocodes to something unrelated, so the city is looked up only from this length. */
    const MIN_CITY_QUERY_LENGTH = 3;

    /** A settlement reads as "City" or "City, region"; anything deeper is an object inside one, not a city. */
    const MAX_CITY_ADDRESS_PARTS = 2;

    /**
     * The geocoder answers with gardening partnerships and villages as readily as with cities, and every one
     * of them names its type first. A city name never starts this way.
     */
    const NON_CITY_PREFIXES = [
        'СНТ', 'СТ ', 'ДНТ', 'ДПК', 'КП ', 'ТСН',
        'SNT', 'ST ', 'DNT', 'DPK', 'KP ', 'TSN',
        'садоводческое', 'садовое товарищество', 'товарищество',
        'деревня', 'село', 'хутор', 'станица', 'аул', 'территория',
    ];

    /**
     * Gets shipping warehouse picker objects.
     *
     * @param int                                                       $shipping_id Shipping method ID
     * @param array<array-key, string|int|float|bool|array|object|null> $params      Picker request params
     *
     * @return array{0: array<int, array<string, string>>, 1: int}
     */
    public function getShippingWarehouseObjects($shipping_id, array $params)
    {
        return $this->getObjects(
            $this->getShippingWarehouses($shipping_id),
            [$this, 'formatShippingWarehouseObject'],
            $params
        );
    }

    /**
     * Gets drop-off warehouse picker objects.
     *
     * @param int                                                       $shipping_id Shipping method ID
     * @param array<array-key, string|int|float|bool|array|object|null> $params      Picker request params
     *
     * @return array{0: array<int, array<string, string>>, 1: int}
     */
    public function getDropoffWarehouseObjects($shipping_id, array $params)
    {
        // An absent key means the picker has not reported a city yet, an empty one means it was cleared on purpose.
        $geo_id = array_key_exists('geo_id', $params)
            ? ExternalValue::int($params, 'geo_id')
            : ExternalValue::int($this->getServiceParams($shipping_id), 'dropoff_geo_id');

        // Nothing chosen yet: the list follows the city suggested in the form, which is the one the shop sits in.
        if (!array_key_exists('geo_id', $params) && $geo_id === 0) {
            $geo_id = ExternalValue::int($this->getDefaultCityObject($shipping_id), 'id');
        }

        return $this->getObjects(
            $this->getDropoffWarehouses($shipping_id, $geo_id),
            [$this, 'formatDropoffWarehouseObject'],
            $params
        );
    }

    /**
     * Gets city picker objects.
     *
     * @param int                                                       $shipping_id Shipping method ID
     * @param array<array-key, string|int|float|bool|array|object|null> $params      Picker request params
     *
     * @return array{0: array<int, array<string, string>>, 1: int}
     *
     * @throws \Exception When the request to Yandex fails.
     */
    public function getCityObjects($shipping_id, array $params)
    {
        $service_params = $this->getServiceParams($shipping_id);

        // Yandex has no method to name a geo_id back, so the picker restores the saved choice from settings.
        if (!empty($params['ids'])) {
            return [$this->getSavedCityObjects($service_params, $params['ids']), 1];
        }

        $query = trim(ExternalValue::text($params, 'q'));
        // The method geocodes a whole name, so a partial one answers with something unrelated instead of nothing.
        if (fn_strlen($query) < self::MIN_CITY_QUERY_LENGTH) {
            return [[], 0];
        }

        $credentials = [
            'auth_token' => ExternalValue::text($service_params, 'auth_token'),
            'test_mode'  => ExternalValue::text($service_params, 'test_mode'),
        ];

        if ($credentials['auth_token'] === '') {
            return [[], 0];
        }

        $client = new YandexDeliveryApiClient($credentials);
        $objects = [];

        $variants = [];

        foreach ($client->getLocationVariants($query) as $variant) {
            $geo_id = ExternalValue::int($variant, 'geo_id');
            $address = trim(ExternalValue::text($variant, 'address'));

            if ($geo_id === 0 || $address === '') {
                continue;
            }

            $variants[] = ['geo_id' => $geo_id, 'address' => $address];
        }

        $cities = array_filter($variants, function (array $variant) {
            return $this->isCityAddress($variant['address']);
        });

        // A gardening partnership stands for the locality it belongs to, and that locality may well have pickup
        // points, so it is dropped only when a plainly named settlement is among the answers as well.
        $addresses = [];

        foreach ($cities ?: $variants as $variant) {
            // One geo_id arrives several times — as the settlement itself and as objects inside it. The shortest
            // address is the settlement.
            if (
                isset($addresses[$variant['geo_id']])
                && fn_strlen($addresses[$variant['geo_id']]) <= fn_strlen($variant['address'])
            ) {
                continue;
            }

            $addresses[$variant['geo_id']] = $variant['address'];
        }

        foreach ($addresses as $geo_id => $address) {
            $objects[$geo_id] = [
                'id' => (string) $geo_id,
                'value' => (string) $geo_id,
                'text' => $address,
                'name' => $address,
            ];
        }

        $objects = array_values($objects);

        return [$objects, count($objects)];
    }

    /**
     * Suggests a city for the drop-off search: the one the shop itself sits in.
     *
     * Parcels are brought to the point by the shop, so its own city is the useful starting point. Cached because
     * the station picker asks for the list on every keystroke while the city is still unset.
     *
     * @param int $shipping_id Shipping method ID
     *
     * @return array<string, string> Identifier and name of the city, empty when it cannot be resolved
     */
    public function getDefaultCityObject($shipping_id)
    {
        $city = trim((string) Registry::get('settings.Company.company_city'));

        if ($city === '') {
            return [];
        }

        $objects = $this->getCachedItems(
            $shipping_id,
            'default_city_' . sha1($city),
            function () use ($shipping_id, $city) {
                try {
                    [$objects] = $this->getCityObjects($shipping_id, ['q' => $city]);
                } catch (Exception $e) {
                    // A city nobody could geocode is no reason to break the settings page.
                    return [];
                }

                return $objects;
            }
        );

        $city_object = $objects === [] ? null : reset($objects);

        if (!is_array($city_object)) {
            return [];
        }

        return [
            'id'   => ExternalValue::text($city_object, 'id'),
            'name' => ExternalValue::text($city_object, 'name'),
        ];
    }

    /**
     * Checks whether a geocoder answer names a settlement rather than an object inside one.
     *
     * @param string $address Address reported by the geocoder
     *
     * @return bool
     */
    protected function isCityAddress($address)
    {
        if (count(explode(',', $address)) > self::MAX_CITY_ADDRESS_PARTS) {
            return false;
        }

        foreach (self::NON_CITY_PREFIXES as $prefix) {
            if (fn_strtolower(fn_substr($address, 0, fn_strlen($prefix))) === fn_strtolower($prefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets the city saved in shipping settings when the picker asks for it by identifier.
     *
     * @param array<array-key, string|int|float|bool|array|object|null> $service_params Shipping service parameters
     * @param string|int|float|bool|array|object|null                   $ids            Requested identifiers
     *
     * @return array<int, array<string, string>>
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getSavedCityObjects(array $service_params, $ids)
    {
        $ids = is_array($ids) ? $ids : explode(',', is_scalar($ids) ? (string) $ids : '');
        $geo_id = ExternalValue::int($service_params, 'dropoff_geo_id');
        $city = trim(ExternalValue::text($service_params, 'dropoff_city'));

        if ($geo_id === 0 || $city === '' || !in_array((string) $geo_id, array_map('strval', $ids), true)) {
            return [];
        }

        return [
            [
                'id' => (string) $geo_id,
                'value' => (string) $geo_id,
                'text' => $city,
                'name' => $city,
            ],
        ];
    }

    /**
     * Gets merchant shipping warehouses.
     *
     * @param int $shipping_id Shipping method ID
     *
     * @return array<int, array<string, string|int|float|bool|array|object|null>>
     */
    protected function getShippingWarehouses($shipping_id)
    {
        return $this->getCachedItems($shipping_id, 'shipping_warehouses', static function (YandexDeliveryApiClient $client) {
            $response = $client->getWarehouses();

            return isset($response['warehouses']) && is_array($response['warehouses'])
                ? $response['warehouses']
                : [];
        });
    }

    /**
     * Gets Yandex points where merchant can bring parcels.
     *
     * @param int $shipping_id Shipping method ID
     * @param int $geo_id      City identifier, zero for the whole country
     *
     * @return array<int, array<string, string|int|float|bool|array|object|null>>
     */
    protected function getDropoffWarehouses($shipping_id, $geo_id = 0)
    {
        $warehouses = $this->getSortingCenters($shipping_id, $geo_id);

        // Pickup points accept parcels too, but there are thousands of them and the method has no paging,
        // so they are asked for only once the city narrows the answer down.
        if ($geo_id <= 0) {
            return $warehouses;
        }

        return array_merge($warehouses, $this->getDropoffPickupPoints($shipping_id, $geo_id));
    }

    /**
     * Gets Yandex sorting centres that accept parcels.
     *
     * @param int $shipping_id Shipping method ID
     * @param int $geo_id      City identifier, zero for the whole country
     *
     * @return array<int, array<string, string|int|float|bool|array|object|null>>
     */
    protected function getSortingCenters($shipping_id, $geo_id)
    {
        $cache_type = $geo_id > 0 ? 'dropoff_warehouses_' . $geo_id : 'dropoff_warehouses';

        return $this->getCachedItems($shipping_id, $cache_type, static function (YandexDeliveryApiClient $client) use ($geo_id) {
            $filter = [
                'type' => PickupStationType::WAREHOUSE,
                'available_for_dropoff' => true,
            ];

            if ($geo_id > 0) {
                $filter['geo_id'] = $geo_id;
            }

            $response = $client->getPickupPoints($filter);

            return isset($response['points']) && is_array($response['points'])
                ? $response['points']
                : [];
        });
    }

    /**
     * Gets pickup points of a city that accept parcels from the merchant.
     *
     * @param int $shipping_id Shipping method ID
     * @param int $geo_id      City identifier
     *
     * @return array<int, array<string, string|int|float|bool|array|object|null>>
     */
    protected function getDropoffPickupPoints($shipping_id, $geo_id)
    {
        return $this->getCachedItems($shipping_id, 'dropoff_points_' . $geo_id, static function (YandexDeliveryApiClient $client) use ($geo_id) {
            $response = $client->getPickupPoints([
                'type' => PickupStationType::PICKUP_POINT,
                'available_for_dropoff' => true,
                'geo_id' => $geo_id,
            ]);

            return isset($response['points']) && is_array($response['points'])
                ? $response['points']
                : [];
        });
    }

    /**
     * Gets cached API response items.
     *
     * @param int      $shipping_id Shipping method ID
     * @param string   $cache_type  Cache type
     * @param callable $loader      API loader
     *
     * @return array<int, array<string, string|int|float|bool|array|object|null>>
     */
    protected function getCachedItems($shipping_id, $cache_type, callable $loader)
    {
        $service_params = $this->getServiceParams($shipping_id);
        $credentials = [
            'auth_token' => ExternalValue::text($service_params, 'auth_token'),
            'test_mode'  => ExternalValue::text($service_params, 'test_mode'),
        ];

        if ($credentials['auth_token'] === '') {
            return [];
        }

        $cache_key = 'yandex_delivery_' . $cache_type . '_' . sha1(implode('|', $credentials));

        return Registry::getOrSetCache(
            $cache_key,
            [
                'update_handlers' => [],
                'ttl' => 300,
            ],
            'static',
            static function () use ($credentials, $loader) {
                return $loader(new YandexDeliveryApiClient($credentials));
            }
        );
    }

    /**
     * Gets Yandex Delivery service params for a shipping method.
     *
     * @param int $shipping_id Shipping method ID
     *
     * @return array<array-key, string|int|float|bool|array|object|null>
     */
    protected function getServiceParams($shipping_id)
    {
        $shipping_info = $shipping_id > 0 ? fn_get_shipping_info($shipping_id, DESCR_SL) : [];
        $service_id = ExternalValue::int($shipping_info, 'service_id');
        $module = $service_id > 0
            ? db_get_field('SELECT module FROM ?:shipping_services WHERE service_id = ?i', $service_id)
            : '';

        return $module === 'yandex_delivery' ? ExternalValue::arr($shipping_info, 'service_params') : [];
    }

    /**
     * Gets picker objects from API items.
     *
     * @param array<int, array<string, string|int|float|bool|array|object|null>> $items         API items
     * @param callable                                                           $format_object Object formatter
     * @param array<array-key, string|int|float|bool|array|object|null>          $params        Picker request params
     *
     * @return array{0: array<int, array<string, string>>, 1: int}
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint
     */
    protected function getObjects(array $items, callable $format_object, array $params)
    {
        $ids = $params['ids'] ?? [];
        $ids = is_array($ids) ? $ids : explode(',', ExternalValue::text($params, 'ids'));
        $ids = array_values(array_filter(array_map(static function ($id) {
            return is_scalar($id) ? trim((string) $id) : '';
        }, $ids)));
        $query = fn_strtolower(trim(ExternalValue::text($params, 'q')));

        /** @var array<int, array<string, string>> $objects */
        $objects = [];

        foreach ($items as $item) {
            /** @var array<string, string> $object */
            $object = $format_object($item);
            if (empty($object)) {
                continue;
            }

            if (!empty($ids) && !in_array($object['id'], $ids, true)) {
                continue;
            }

            if (empty($ids) && $query !== '' && strpos(fn_strtolower($object['search_text']), $query) === false) {
                continue;
            }

            $objects[] = $object;
        }

        usort($objects, static function (array $left, array $right) {
            $group = strcmp($left['sort_group'] ?? '', $right['sort_group'] ?? '');

            return $group === 0 ? strnatcasecmp($left['text'], $right['text']) : $group;
        });

        $total_objects = count($objects);
        if (empty($ids)) {
            $page = max(1, ExternalValue::int($params, 'page'));
            $page_size = max(1, min(100, ExternalValue::int($params, 'page_size') ?: 10));
            $objects = array_slice($objects, ($page - 1) * $page_size, $page_size);
        }

        foreach ($objects as &$object) {
            unset($object['search_text'], $object['sort_group']);
        }
        unset($object);

        return [$objects, $total_objects];
    }

    /**
     * Formats a shipping warehouse for object picker.
     *
     * @param string|int|float|bool|array<string, string|int|float|bool|array|object|null>|object|null $warehouse API warehouse data
     *
     * @return array<string, string>
     */
    protected function formatShippingWarehouseObject($warehouse)
    {
        if (!is_array($warehouse) || empty($warehouse['station_id'])) {
            return [];
        }

        $station_id = trim(ExternalValue::text($warehouse, 'station_id'));
        $name = trim(ExternalValue::text($warehouse, 'name'));
        $client_warehouse_id = trim(ExternalValue::text($warehouse, 'client_warehouse_id'));
        $warehouse_address = ExternalValue::arr(ExternalValue::arr($warehouse, 'location'), 'address');
        $address = $this->formatAddress($warehouse_address);
        $text = $name !== ''
            ? implode(' - ', array_filter([$name, $address]))
            : ($address !== '' ? $address : ($client_warehouse_id !== '' ? $client_warehouse_id : $station_id));
        $title = $name !== '' ? $name : $text;

        return [
            'id' => $station_id,
            'value' => $station_id,
            'text' => $text,
            'name' => $title,
            'station_id' => $station_id,
            'client_warehouse_id' => $client_warehouse_id,
            'address' => $address,
            'search_text' => implode(' ', array_filter([
                $text,
                $client_warehouse_id,
                $this->scalarValuesToString($warehouse_address),
            ])),
        ];
    }

    /**
     * Formats a drop-off warehouse for object picker.
     *
     * @param string|int|float|bool|array<string, string|int|float|bool|array|object|null>|object|null $point API pickup point data
     *
     * @return array<string, string|array<string, string>>
     */
    protected function formatDropoffWarehouseObject($point)
    {
        if (!is_array($point) || empty($point['id'])) {
            return [];
        }

        // A station with no logistics operator is the merchant's own warehouse: Yandex lists it, but refuses
        // to price a shipment from it until a pickup is scheduled.
        if (ExternalValue::text($point, 'operator_id') === '') {
            return [];
        }

        $id = trim(ExternalValue::text($point, 'id'));
        $name = trim(ExternalValue::text($point, 'name'));
        $address = $this->formatAddress(ExternalValue::arr($point, 'address'));
        $text = $name !== ''
            ? implode(' - ', array_filter([$name, $address]))
            : ($address !== '' ? $address : $id);
        $title = $name !== '' ? $name : $text;
        $is_pickup_point = ExternalValue::text($point, 'type') === PickupStationType::PICKUP_POINT;

        return [
            'id' => $id,
            'value' => $id,
            'text' => $text,
            'name' => $title,
            'station_id' => $id,
            'address' => $address,
            // Sorting centres take any volume, so they must not drown among a thousand pickup points.
            'sort_group' => $is_pickup_point ? '1' : '0',
            // The picker interpolates its item template from this key only, not from the object itself.
            'data' => [
                'name' => $title,
                'address' => $address,
                'type_label' => $is_pickup_point
                    ? __('yandex_delivery.station_type.pickup_point')
                    : __('yandex_delivery.station_type.warehouse'),
                'type_class' => $is_pickup_point ? 'pickup-point' : 'warehouse',
            ],
            'search_text' => implode(' ', array_filter([
                $text,
                $this->scalarValuesToString($point['operator_station_id'] ?? ''),
                $this->scalarValuesToString($point['address'] ?? []),
            ])),
        ];
    }

    /**
     * Formats warehouse address.
     *
     * @param string|int|float|bool|array<array-key, string|int|float|bool|array|object|null>|object|null $address Address data
     *
     * @return string
     */
    protected function formatAddress($address)
    {
        if (!is_array($address)) {
            return '';
        }

        if (!empty($address['full_address']) && is_scalar($address['full_address'])) {
            return trim((string) $address['full_address']);
        }

        $street = $this->scalarValuesToString([
            $address['street'] ?? '',
            $address['house'] ?? '',
        ]);

        return implode(', ', array_unique(array_filter([
            $this->scalarValuesToString($address['city'] ?? ''),
            $this->scalarValuesToString($address['locality'] ?? ''),
            $this->scalarValuesToString($address['region'] ?? ''),
            $street,
            $this->scalarValuesToString($address['postal_code'] ?? ''),
        ])));
    }

    /**
     * Converts scalar values to a searchable string.
     *
     * @param string|int|float|bool|array<array-key, string|int|float|bool|array|object|null>|null $values Scalar value or list of scalar values
     *
     * @return string
     */
    protected function scalarValuesToString($values)
    {
        if (!is_array($values)) {
            return is_scalar($values) ? trim((string) $values) : '';
        }

        return implode(' ', array_filter(array_map(static function ($value) {
            return is_scalar($value) ? trim((string) $value) : '';
        }, $values)));
    }
}
