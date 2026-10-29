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
use Tygh\Addons\YandexDelivery\Enum\DeliveryType;
use Tygh\Enum\YesNo;
use Exception;

/**
 * Builds Yandex Delivery API payloads from shipping method data.
 */
class ShippingDataBuilder
{
    /** The currency Yandex bills in. The core converts the cart total into it before every calculation. */
    const BILLING_CURRENCY = 'RUB';

    const PAYMENT_METHOD_ALREADY_PAID = 'already_paid';

    /** @var WeightConverter */
    protected $weight_converter;

    /**
     * @param WeightConverter $weight_converter Turns product weight into the grams Yandex expects
     *
     * @return void
     */
    public function __construct(WeightConverter $weight_converter)
    {
        $this->weight_converter = $weight_converter;
    }

    /**
     * Builds payload for the pricing calculator.
     *
     * @param array<string, string|int|float|bool|array|null> $shipping_info Shipping data
     *
     * @return array{
     *     source: array{platform_station_id: string},
     *     destination: array{platform_station_id: string}|array{address: string},
     *     tariff: string,
     *     total_weight: int,
     *     total_assessed_price: int,
     *     payment_method: string,
     *     places: array<int, array{physical_dims: array{weight_gross: int, dx: int, dy: int, dz: int}}>
     * }
     *
     * @throws \Exception When required shipping data is missing.
     */
    public function buildPricingCalculatorPayload(array $shipping_info)
    {
        $service_params = $shipping_info['service_params'] ?? [];
        $source_platform_station_id = $this->getSourcePlatformStationId($service_params);

        if ($source_platform_station_id === '') {
            throw new Exception($this->getSourcePlatformStationRequiredMessage($service_params));
        }

        $package_info = $shipping_info['package_info'] ?? [];
        $places = $this->buildPlaces($shipping_info);
        $total_weight = 0;

        foreach ($places as $place) {
            $total_weight += ExternalValue::int(ExternalValue::arr($place, 'physical_dims'), 'weight_gross');
        }

        return [
            'source' => [
                'platform_station_id' => $source_platform_station_id,
            ],
            'destination' => $this->buildDestination($shipping_info),
            'tariff' => $this->getDeliveryType($service_params),
            'total_weight' => $total_weight,
            'total_assessed_price' => $this->priceToMinorUnits($package_info['C'] ?? 0),
            // The order is always paid to the shop at checkout, so Yandex collects nothing on handover.
            'payment_method' => self::PAYMENT_METHOD_ALREADY_PAID,
            'places' => $places,
        ];
    }

    /**
     * Builds a readable address from platform location data.
     *
     * @param array<string, string|int|float|null> $location Location data
     *
     * @return string
     */
    public function buildAddress(array $location)
    {
        $parts = [];

        // Postal code goes first on purpose: Yandex reads a trailing "..., 19, 115477" as a coordinate pair and rejects the address.
        foreach (['zipcode', 'city', 'address'] as $field) {
            if (!empty($location[$field])) {
                $parts[] = trim((string) $location[$field]);
            }
        }

        if (empty($parts)) {
            foreach (['state', 'country'] as $field) {
                if (!empty($location[$field])) {
                    $parts[] = trim((string) $location[$field]);
                }
            }
        }

        $parts = array_unique(array_filter($parts));

        return implode(', ', $parts);
    }

    /**
     * Gets the selected Yandex Delivery tariff.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return string
     */
    public function getDeliveryType(array $service_params)
    {
        $delivery_type = ExternalValue::text($service_params, 'delivery_type') ?: DeliveryType::TIME_INTERVAL;

        return DeliveryType::isPickup($delivery_type) ? DeliveryType::SELF_PICKUP : DeliveryType::TIME_INTERVAL;
    }

    /**
     * Checks whether the merchant delivers parcels to a Yandex drop-off warehouse.
     *
     * This is the default: it needs no warehouse of our own, no scheduled pickup and no city coverage,
     * so a freshly configured method quotes right away.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return bool
     */
    public function isDropoffEnabled(array $service_params)
    {
        return YesNo::toBool(ExternalValue::text($service_params, 'use_dropoff') ?: YesNo::YES);
    }

    /**
     * Gets source platform station ID for pricing and request creation.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return string
     */
    public function getSourcePlatformStationId(array $service_params)
    {
        $field = $this->isDropoffEnabled($service_params)
            ? 'dropoff_platform_station_id'
            : 'source_platform_station_id';

        return trim((string) ($service_params[$field] ?? ''));
    }

    /**
     * Gets the missing source station error message.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return string
     */
    public function getSourcePlatformStationRequiredMessage(array $service_params)
    {
        return $this->isDropoffEnabled($service_params)
            ? __('yandex_delivery.dropoff_platform_station_id_required')
            : __('yandex_delivery.source_platform_station_id_required');
    }

    /**
     * Builds delivery destination.
     *
     * @param array<string, string|int|float|bool|array|null> $shipping_info Shipping data
     *
     * @return array{platform_station_id: string}|array{address: string}
     *
     * @throws \Exception When destination is missing.
     */
    protected function buildDestination(array $shipping_info)
    {
        $service_params = $shipping_info['service_params'] ?? [];
        $selected_pickup_point = trim(ExternalValue::text($shipping_info, 'select_pickup_point'));

        if (
            DeliveryType::isPickup($this->getDeliveryType($service_params))
            && $selected_pickup_point !== ''
        ) {
            return ['platform_station_id' => $selected_pickup_point];
        }

        $address = $this->buildAddress($shipping_info['package_info']['location'] ?? []);
        if ($address === '') {
            throw new Exception(__('yandex_delivery.destination_address_required'));
        }

        return ['address' => $address];
    }

    /**
     * Builds parcel places.
     *
     * @param array<string, string|int|float|bool|array|null> $shipping_info Shipping data
     *
     * @return array<int, array{physical_dims: array{weight_gross: int, dx: int, dy: int, dz: int}}>
     */
    protected function buildPlaces(array $shipping_info)
    {
        $package_info = $shipping_info['package_info'] ?? [];
        $packages = $package_info['packages'] ?? [];

        if (empty($packages)) {
            $packages = [
                [
                    'weight' => $package_info['W'] ?? 0,
                    'shipping_params' => [],
                ],
            ];
        }

        $places = [];
        foreach ($packages as $package) {
            $places[] = [
                'physical_dims' => $this->buildPhysicalDimensions($package, $shipping_info['service_params'] ?? []),
            ];
        }

        return $places;
    }

    /**
     * Builds physical dimensions.
     *
     * @param array<string, string|int|float|array|null> $package        Package data
     * @param array<string, string|int|float|bool|null>  $service_params Shipping service parameters
     *
     * @return array{weight_gross: int, dx: int, dy: int, dz: int}
     *
     * @throws \Exception When a dimension is set neither on the package nor in the shipping settings.
     */
    public function buildPhysicalDimensions(array $package, array $service_params)
    {
        $shipping_params = $package['shipping_params'] ?? [];

        return [
            'weight_gross' => $this->weight_converter->toGrams(ExternalValue::float($package, 'weight')),
            'dx' => $this->getDimension($shipping_params, $service_params, 'box_length', 'length'),
            'dy' => $this->getDimension($shipping_params, $service_params, 'box_width', 'width'),
            'dz' => $this->getDimension($shipping_params, $service_params, 'box_height', 'height'),
        ];
    }

    /**
     * Gets a dimension value from package data or shipping service settings.
     *
     * @param array<string, string|int|float|null>      $shipping_params       Package shipping parameters
     * @param array<string, string|int|float|bool|null> $service_params        Shipping service parameters
     * @param string                                    $package_dimension_key Package dimension key
     * @param string                                    $service_dimension_key Shipping service dimension key
     *
     * @return int
     *
     * @throws \Exception When dimension is missing.
     */
    protected function getDimension(
        array $shipping_params,
        array $service_params,
        $package_dimension_key,
        $service_dimension_key
    ) {
        $dimension = $this->normalizeDimension($shipping_params[$package_dimension_key] ?? 0);

        if ($dimension === 0) {
            $dimension = $this->normalizeDimension($service_params[$service_dimension_key] ?? 0);
        }

        if ($dimension === 0) {
            throw new Exception(__('yandex_delivery.dimensions_required'));
        }

        return $dimension;
    }


    /**
     * Normalizes dimension in centimeters the way the core normalizes a product box: whole numbers only,
     * and zero for anything unusable, which the caller reads as "not set".
     *
     * @param float|int|string|bool|null $dimension Dimension value
     *
     * @return int
     *
     * @see \fn_update_product()
     */
    public function normalizeDimension($dimension)
    {
        return max((int) $dimension, 0);
    }

    /**
     * Converts price to minor currency units.
     *
     * @param float|int|string $price Price in rubles
     *
     * @return int
     */
    public function priceToMinorUnits($price)
    {
        return max((int) round((float) $price * 100), 0);
    }
}
