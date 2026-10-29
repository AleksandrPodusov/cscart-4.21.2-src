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
use Tygh\Registry;
use Tygh\Shippings\Shippings;
use Exception;

/**
 * Builds Yandex Delivery request payloads from shipments.
 */
class RequestDataBuilder
{
    const DESTINATION_PLATFORM_STATION = 'platform_station';
    const DESTINATION_CUSTOM_LOCATION = 'custom_location';

    /** @var OrderTaxRates */
    protected $order_tax_rates;

    /** @var ShippingDataBuilder */
    protected $shipping_data_builder;

    /**
     * @param OrderTaxRates       $order_tax_rates       Resolves the VAT rate reported for every product
     * @param ShippingDataBuilder $shipping_data_builder Builds the payload fragments shared with rate calculation
     *
     * @return void
     */
    public function __construct(OrderTaxRates $order_tax_rates, ShippingDataBuilder $shipping_data_builder)
    {
        $this->order_tax_rates = $order_tax_rates;
        $this->shipping_data_builder = $shipping_data_builder;
    }

    /**
     * Builds payload for the request/create method.
     *
     * @param array $order_info    Order data
     * @param array $shipment      Shipment data
     * @param array $shipping_info Shipping method data
     *
     * @return array
     *
     * @throws \Exception When required data is missing.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function build(array $order_info, array $shipment, array $shipping_info)
    {
        $service_params = $shipping_info['service_params'] ?? [];
        $source_platform_station_id = $this->shipping_data_builder->getSourcePlatformStationId($service_params);

        if ($source_platform_station_id === '') {
            throw new Exception($this->shipping_data_builder->getSourcePlatformStationRequiredMessage($service_params));
        }

        $order_shipping = $this->findOrderShipping($order_info, $shipment);
        $barcode_prefix = 'y' . base_convert((string) ExternalValue::int($shipment, 'shipment_id'), 10, 36) . '-';
        $shipment_content = $this->buildShipmentContent($order_info, $shipment, $service_params, $barcode_prefix);

        return [
            'info' => $this->buildRequestInfo($order_info, $shipment),
            'source' => [
                'platform_station' => [
                    'platform_id' => $source_platform_station_id,
                ],
                'interval_utc' => null,
            ],
            'destination' => $this->buildDestination($order_info, $order_shipping, $service_params),
            'items' => $shipment_content['items'],
            'places' => $shipment_content['places'],
            'billing_info' => [
                // The order is paid to the shop at checkout, so Yandex collects nothing from the recipient.
                'payment_method' => ShippingDataBuilder::PAYMENT_METHOD_ALREADY_PAID,
                'delivery_cost' => 0,
            ],
            'recipient_info' => $this->buildRecipientInfo($order_info),
            'last_mile_policy' => $this->shipping_data_builder->getDeliveryType($service_params),
            'particular_items_refuse' => false,
            'forbid_unboxing' => false,
        ];
    }

    /**
     * Gets the operator request ID that must be unique for the Yandex request.
     *
     * It stays the same across retries on purpose: Yandex answers a repeat with `208` and the request_id it
     * already holds, which is what keeps a lost answer from becoming a second parcel.
     *
     * @param array{order_id: int|string}    $order_info Order data
     * @param array{shipment_id: int|string} $shipment   Shipment data
     *
     * @return string
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getOperatorRequestId(array $order_info, array $shipment)
    {
        return 'yd-' . (int) $order_info['order_id'] . '-' . (int) $shipment['shipment_id'];
    }

    /**
     * Builds request metadata.
     *
     * @param array{order_id: int|string, notes?: string}       $order_info Order data
     * @param array{shipment_id: int|string, comments?: string} $shipment   Shipment data
     *
     * @return array{operator_request_id: string, comment?: string}
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function buildRequestInfo(array $order_info, array $shipment)
    {
        $info = [
            'operator_request_id' => $this->getOperatorRequestId($order_info, $shipment),
        ];

        $comment = trim(ExternalValue::text($shipment, 'comments'));
        if ($comment === '' && !empty($order_info['notes'])) {
            $comment = trim(ExternalValue::text($order_info, 'notes'));
        }

        if ($comment !== '') {
            $info['comment'] = $comment;
        }

        return $info;
    }

    /**
     * Builds delivery destination.
     *
     * @param array<string, string|int|float|null>                          $order_info     Order data
     * @param array<string, string|int|array<string, string|int|null>|null> $order_shipping Order shipping data
     * @param array<string, string|int|float|bool|null>                     $service_params Shipping service parameters
     *
     * @return array{
     *     type: string,
     *     platform_station: array{platform_id: string}|null,
     *     custom_location: array{details: array<string, string>}|null,
     *     interval_utc: null
     * }
     *
     * @throws \Exception When destination is missing.
     */
    protected function buildDestination(array $order_info, array $order_shipping, array $service_params)
    {
        if (DeliveryType::isPickup($this->shipping_data_builder->getDeliveryType($service_params))) {
            $pickup_point_id = $this->getPickupPointId($order_shipping);
            if ($pickup_point_id === '') {
                throw new Exception(__('yandex_delivery.pickup_point_required'));
            }

            return [
                'type' => self::DESTINATION_PLATFORM_STATION,
                'platform_station' => [
                    'platform_id' => $pickup_point_id,
                ],
                'custom_location' => null,
                'interval_utc' => null,
            ];
        }

        $details = $this->buildLocationDetails($order_info);
        if (empty($details['full_address'])) {
            throw new Exception(__('yandex_delivery.destination_address_required'));
        }

        return [
            'type' => self::DESTINATION_CUSTOM_LOCATION,
            'platform_station' => null,
            'custom_location' => [
                'details' => $details,
            ],
            'interval_utc' => null,
        ];
    }

    /**
     * Builds destination address details.
     *
     * @param array<string, string|int|float|null> $order_info Order data
     *
     * @return array<string, string>
     */
    protected function buildLocationDetails(array $order_info)
    {
        $address = trim(implode(' ', array_filter([
            ExternalValue::text($order_info, 's_address'),
            ExternalValue::text($order_info, 's_address_2'),
        ])));

        $country = ExternalValue::text($order_info, 's_country_descr') ?: ExternalValue::text($order_info, 's_country');
        $region = ExternalValue::text($order_info, 's_state_descr') ?: ExternalValue::text($order_info, 's_state');
        $city = ExternalValue::text($order_info, 's_city');
        $zipcode = ExternalValue::text($order_info, 's_zipcode');

        $details = [
            'country' => $country,
            'region' => $region,
            'locality' => $city,
            'full_address' => $this->shipping_data_builder->buildAddress([
                'city' => $city,
                'address' => $address,
                'zipcode' => $zipcode,
                'state' => $region,
                'country' => $country,
            ]),
            'postal_code' => $zipcode,
        ];

        return $this->filterEmptyValues($details);
    }

    /**
     * Builds recipient information.
     *
     * @param array<string, string|int|float|null> $order_info Order data
     *
     * @return array<string, string>
     *
     * @throws \Exception When recipient phone is missing.
     */
    protected function buildRecipientInfo(array $order_info)
    {
        $phone = $this->getFirstNotEmpty($order_info, ['s_phone', 'phone', 'b_phone']);

        if ($phone === '') {
            throw new Exception(__('yandex_delivery.recipient_phone_required'));
        }

        $recipient = [
            'first_name' => $this->getFirstNotEmpty($order_info, ['s_firstname', 'firstname']),
            'last_name' => $this->getFirstNotEmpty($order_info, ['s_lastname', 'lastname']),
            'phone' => $phone,
            'email' => trim(ExternalValue::text($order_info, 'email')),
        ];

        if ($recipient['first_name'] === '' && $recipient['last_name'] === '') {
            $recipient['first_name'] = 'Customer';
        }

        return $this->filterEmptyValues($recipient);
    }

    /**
     * Converts an order price into the minor units of the currency Yandex bills in.
     *
     * A rate calculation gets the cart total converted by the core, which knows the service currency. A
     * shipment request is built outside that path, so order prices are still in the store primary currency.
     *
     * @param string|int|float|bool|array|null $price Price in the store primary currency
     *
     * @return int
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function priceToBillingMinorUnits($price)
    {
        $price = is_scalar($price) ? (float) $price : 0.0;
        $currencies = (array) Registry::get('currencies');

        // Asked for a currency the store does not have, the core silently converts into the secondary one
        // instead, so the conversion is only made when the billing currency is really there.
        if (
            defined('CART_PRIMARY_CURRENCY')
            && CART_PRIMARY_CURRENCY !== ShippingDataBuilder::BILLING_CURRENCY
            && !empty($currencies[ShippingDataBuilder::BILLING_CURRENCY])
        ) {
            $price = fn_format_price_by_currency(
                $price,
                CART_PRIMARY_CURRENCY,
                ShippingDataBuilder::BILLING_CURRENCY
            );
        }

        return $this->shipping_data_builder->priceToMinorUnits($price);
    }

    /**
     * Builds items and places.
     *
     * @param array<string, string|int|float|array|null> $order_info     Order data
     * @param array<string, string|int|float|array|null> $shipment       Shipment data
     * @param array<string, string|int|float|bool|null>  $service_params Shipping service parameters
     * @param string                                     $barcode_prefix Prefix for place barcodes
     *
     * @return array{
     *     items: array<int, array{
     *         count: int,
     *         name: string,
     *         article: string,
     *         billing_details: array{nds: int, unit_price: int, assessed_unit_price: int},
     *         physical_dims: array{weight_gross: int, dx: int, dy: int, dz: int},
     *         place_barcode: string,
     *         fitting: bool,
     *         refused_count: int
     *     }>,
     *     places: array<int, array{
     *         physical_dims: array{weight_gross: int, dx: int, dy: int, dz: int},
     *         barcode: string
     *     }>
     * }
     *
     * @throws \Exception When shipment has no products.
     */
    protected function buildShipmentContent(
        array $order_info,
        array $shipment,
        array $service_params,
        $barcode_prefix
    ) {
        $product_rows = $this->getProductRows($order_info, $shipment);
        $packages = $this->buildPackages($order_info, $shipment, $product_rows);
        $tax_rates = $this->order_tax_rates->getByCartId($order_info);
        $items = [];
        $places = [];
        $place_number = 0;

        foreach ($packages as $package) {
            $place_number++;
            $barcode = $barcode_prefix . $place_number;
            $places[] = [
                'physical_dims' => $this->shipping_data_builder->buildPhysicalDimensions($package, $service_params),
                'barcode' => $barcode,
            ];

            foreach ($package['products'] ?? [] as $item_id => $amount) {
                if ($amount <= 0 || empty($order_info['products'][$item_id])) {
                    continue;
                }

                $order_product = $order_info['products'][$item_id];
                $product_row = $product_rows[(int) ($order_product['product_id'] ?? 0)] ?? [];

                $items[] = [
                    'count' => $amount,
                    'name' => trim(strip_tags(ExternalValue::text($order_product, 'product'))) ?: __('product'),
                    'article' => trim(ExternalValue::text($order_product, 'product_code'))
                        ?: (string) ExternalValue::int($order_product, 'product_id'),
                    'billing_details' => [
                        'nds' => $tax_rates[$item_id] ?? OrderTaxRates::NDS_NONE,
                        'unit_price' => $this->priceToBillingMinorUnits($order_product['price'] ?? null),
                        'assessed_unit_price' => $this->priceToBillingMinorUnits($order_product['price'] ?? null),
                    ],
                    'physical_dims' => $this->shipping_data_builder->buildPhysicalDimensions(
                        [
                            'weight' => $this->getProductWeight($order_product, $product_row),
                            'shipping_params' => $this->getProductShippingParams($product_row),
                        ],
                        $service_params
                    ),
                    'place_barcode' => $barcode,
                    'fitting' => false,
                    'refused_count' => 0,
                ];
            }
        }

        if (empty($items) || empty($places)) {
            throw new Exception(__('yandex_delivery.shipment_products_required'));
        }

        return [
            'items' => $items,
            'places' => $places,
        ];
    }

    /**
     * Splits shipment products into parcels the same way the shipping rate calculation does.
     *
     * Reuses the core packaging so that the request describes exactly what the customer was quoted for.
     *
     * @param array<string, string|int|float|array|null>       $order_info   Order data
     * @param array<string, string|int|float|array|null>       $shipment     Shipment data
     * @param array<int, array<string, string|int|float|null>> $product_rows Product rows data
     *
     * @return array<int, array{
     *     products: array<int|string, int>,
     *     weight: float|int,
     *     shipping_params?: array<string, string|int|float|null>
     * }>
     */
    protected function buildPackages(array $order_info, array $shipment, array $product_rows)
    {
        $products = [];
        $company_names = [];

        foreach ((array) ($shipment['products'] ?? []) as $item_id => $amount) {
            $amount = (int) $amount;
            if ($amount <= 0 || empty($order_info['products'][$item_id])) {
                continue;
            }

            $order_product = $order_info['products'][$item_id];
            $product_id = (int) ($order_product['product_id'] ?? 0);
            $product_row = $product_rows[$product_id] ?? [];

            $company_id = $order_product['company_id'] ?? $order_info['company_id'] ?? 0;
            $company_id = is_scalar($company_id) ? (int) $company_id : 0;

            // Grouping is a cart-side routine, and add-ons hooked into it read the vendor name off every product.
            if (!isset($company_names[$company_id])) {
                $company_names[$company_id] = fn_get_company_name($company_id);
            }

            $products[$item_id] = [
                'product_id'      => $product_id,
                'company_id'      => $company_id,
                'company_name'    => $company_names[$company_id],
                'amount'          => $amount,
                'weight'          => $this->getProductWeight($order_product, $product_row),
                'price'           => (float) ($order_product['price'] ?? 0),
                'is_edp'          => $order_product['is_edp'] ?? 'N',
                'edp_shipping'    => $order_product['edp_shipping'] ?? 'N',
                'shipping_params' => $this->getProductShippingParams($product_row),
            ];
        }

        if (empty($products)) {
            return [];
        }

        $location = [
            'country' => $order_info['s_country'] ?? '',
            'state'   => $order_info['s_state'] ?? '',
            'city'    => $order_info['s_city'] ?? '',
            'address' => $order_info['s_address'] ?? '',
            'zipcode' => $order_info['s_zipcode'] ?? '',
        ];

        $packages = [];
        foreach (Shippings::groupProductsList($products, $location) as $group) {
            foreach ($group['package_info_full']['packages'] ?? [] as $package) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    /**
     * Finds order shipping data for the shipment.
     *
     * @param array $order_info Order data
     * @param array $shipment   Shipment data
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function findOrderShipping(array $order_info, array $shipment)
    {
        $shipment_shipping_id = (int) ($shipment['shipping_id'] ?? 0);
        $shipment_group_key = (string) ($shipment['group_key'] ?? '');

        foreach ((array) ($order_info['shipping'] ?? []) as $shipping) {
            if ((int) ($shipping['shipping_id'] ?? 0) !== $shipment_shipping_id) {
                continue;
            }

            if ($shipment_group_key === '' || (string) ($shipping['group_key'] ?? '') === $shipment_group_key) {
                return $shipping;
            }
        }

        foreach ((array) ($order_info['shipping'] ?? []) as $shipping) {
            if ((int) ($shipping['shipping_id'] ?? 0) === $shipment_shipping_id) {
                return $shipping;
            }
        }

        return [];
    }

    /**
     * Gets selected pickup point ID from stored order shipping data.
     *
     * @param array<string, string|int|float|bool|array|object|null> $order_shipping Order shipping data
     *
     * @return string
     */
    protected function getPickupPointId(array $order_shipping)
    {
        foreach (['pickup_point_id', 'select_pickup_point'] as $field) {
            if (!empty($order_shipping[$field])) {
                return trim(ExternalValue::text($order_shipping, $field));
            }
        }

        $pickup_point_data = $order_shipping['pickup_point_data'] ?? [];
        if (!is_array($pickup_point_data)) {
            $pickup_point_data = [];
        }
        foreach (['platform_station_id', 'id', 'code'] as $field) {
            if (!empty($pickup_point_data[$field])) {
                return trim((string) $pickup_point_data[$field]);
            }
        }

        return '';
    }

    /**
     * Gets product rows needed to build shipment items.
     *
     * @param array $order_info Order data
     * @param array $shipment   Shipment data
     *
     * @return array<int, array<string, string|int|float|null>>
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getProductRows(array $order_info, array $shipment)
    {
        $product_ids = [];
        foreach (array_keys((array) ($shipment['products'] ?? [])) as $item_id) {
            if (!empty($order_info['products'][$item_id]['product_id'])) {
                $product_ids[] = (int) $order_info['products'][$item_id]['product_id'];
            }
        }

        $product_ids = array_unique(array_filter($product_ids));
        if (empty($product_ids)) {
            return [];
        }

        return db_get_hash_array(
            'SELECT product_id, weight, shipping_params FROM ?:products WHERE product_id IN (?n)',
            'product_id',
            $product_ids
        );
    }

    /**
     * Gets product weight in store units.
     *
     * @param array<string, string|int|float|array|null> $order_product Order product data
     * @param array<string, string|int|float|null>       $product_row   Product row data
     *
     * @return float
     */
    protected function getProductWeight(array $order_product, array $product_row)
    {
        $weight = ExternalValue::float($product_row, 'weight') ?: ExternalValue::float($order_product, 'weight');
        $product_options = $order_product['extra']['product_options'] ?? [];

        if (!empty($product_options)) {
            $weight = (float) fn_apply_options_modifiers($product_options, $weight, 'W');
        }

        return $weight;
    }

    /**
     * Gets product shipping parameters.
     *
     * @param array<string, string|int|float|null> $product_row Product row data
     *
     * @return array<string, string|int|float|bool|array|null>
     */
    protected function getProductShippingParams(array $product_row)
    {
        if (empty($product_row['shipping_params'])) {
            return [];
        }

        $shipping_params = @unserialize(ExternalValue::text($product_row, 'shipping_params'));

        return is_array($shipping_params) ? $shipping_params : [];
    }




    /**
     * Removes empty values without dropping zeroes.
     *
     * @param array<string, string> $data Source data
     *
     * @return array<string, string>
     */
    protected function filterEmptyValues(array $data)
    {
        return array_filter($data, static function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * Gets the first non-empty string field value.
     *
     * @param array<string, string|int|float|null> $data   Source data
     * @param string[]                             $fields Field names
     *
     * @return string
     */
    protected function getFirstNotEmpty(array $data, array $fields)
    {
        foreach ($fields as $field) {
            if (!empty($data[$field])) {
                return trim((string) $data[$field]);
            }
        }

        return '';
    }
}
