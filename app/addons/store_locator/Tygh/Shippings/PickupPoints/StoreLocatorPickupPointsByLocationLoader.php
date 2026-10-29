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

namespace Tygh\Shippings\PickupPoints;

use Tygh\Enum\ObjectStatuses;
use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Shippings\PickupPoints\Dto\PickupPoint;
use Tygh\Shippings\PickupPoints\Factories\ShippingInfoFactory;
use Tygh\Shippings\PickupPoints\LoaderByLocation\IPickupPointsLoaderByLocation;
use Tygh\Shippings\Shippings;

class StoreLocatorPickupPointsByLocationLoader implements IPickupPointsLoaderByLocation
{
    /** @var int|null */
    public $company_id = null;

    /**
     * @param PickupPointsLookupContext $lookup_context Lookup context for pickup points search
     *
     * @return PickupPoint[]|array
     */
    public function getPickupPointsByLocation(PickupPointsLookupContext $lookup_context)
    {
        $this->company_id = $lookup_context->getCompanyId();

        $destination_id = fn_get_available_destination(
            [
                'city' => $lookup_context->getLocation()->getCity(),
                'country' => $lookup_context->getLocation()->getCountry(),
                'state' => $lookup_context->getLocation()->getState(),
                'zipcode' => $lookup_context->getLocation()->getZipcode(),
            ]
        );

        if (empty($destination_id)) {
            return [];
        }

        static $cache = [];

        $cache_key = md5(implode('|', [
            $this->company_id,
            $destination_id,
            $lookup_context->getShippingId(),
            CART_LANGUAGE
        ]));

        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $active_stores = $this->getActiveStores($destination_id, $this->company_id);

        if (empty($active_stores)) {
            return [];
        }

        $shipping_info = ShippingInfoFactory::buildFromContext($lookup_context);

        $destination_rates = fn_get_shipping_destinations($shipping_info['shipping_id'], $shipping_info, CART_LANGUAGE);

        $available_stores = $this->extractAvailableStores($destination_rates, $active_stores, $shipping_info);
        if (empty($available_stores)) {
            return [];
        }

        $pickup_points = [];

        foreach ($available_stores as $code => $store) {
            $pickup_point = PickupPoint::create(
                $code,
                $shipping_info['module'] ?? 'store_locator',
                '',
                $store['pickup_address'],
                (float) $store['longitude'],
                (float) $store['latitude'],
                $store,
                $store['name'],
                $store['city'],
                null,
                $store['pickup_time'],
                $store['pickup_phone'],
                $store['description']
            );

            $pickup_points[] = $pickup_point;
        }

        $cache[$cache_key] = $pickup_points;

        return $pickup_points;
    }

    /**
     * Fetches active stores
     *
     * @param int $destination_id Destination identifiers
     * @param int $company_id     Company identifier
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getActiveStores($destination_id, $company_id)
    {
        $joins = [];
        $fields = ['all' => '*'];
        $joins['store_location_descriptions'] = db_quote(
            'LEFT JOIN ?:store_location_descriptions as descriptions ON locations.store_location_id = descriptions.store_location_id'
        );
        $joins['destinations'] = db_quote('JOIN ?:destinations as destinations ON locations.main_destination_id = destinations.destination_id');
        $conditions = [];
        $conditions['status'] = db_quote('locations.status = ?s AND descriptions.lang_code = ?s', 'A', CART_LANGUAGE);
        $conditions['destinations_status'] = db_quote('destinations.status = ?s', ObjectStatuses::ACTIVE);
        $conditions['destination_ids'] = db_quote('(?p)', fn_find_array_in_set([$destination_id], 'locations.pickup_destinations_ids', true));
        $conditions['main_destination_id'] = db_quote('main_destination_id IS NOT NULL');

        if (fn_allowed_for('MULTIVENDOR')) {
            $conditions['company_id'] = db_quote('locations.company_id = ?i', $company_id);
        }

        /**
         * Executes before fetching available stores list for shipping
         *
         * @param int                                                                   $destination_id Destination identifier
         * @param array                                                                 $fields         Fields to select
         * @param array                                                                 $joins          Tables to join
         * @param array                                                                 $conditions     Query conditions
         * @param \Tygh\Shippings\PickupPoints\StoreLocatorPickupPointsByLocationLoader $this           Current state of object
         */
        fn_set_hook('get_store_locations_for_shipping_before_select', $destination_id, $fields, $joins, $conditions, $this);

        return db_get_hash_array(
            'SELECT ?p FROM ?:store_locations as locations ?p WHERE 1=1 AND ?p',
            'store_location_id',
            implode(', ', $fields),
            implode(' ', $joins),
            implode(' AND ', $conditions)
        );
    }

    /**
     * Extracts stores that are available for shipping rates
     *
     * @param array $destination_rates Shipping rates
     * @param array $active_stores     Active stores
     * @param array $shipping_info     Shipping data
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function extractAvailableStores(array $destination_rates, array $active_stores, array $shipping_info)
    {
        $stores = [];

        foreach ($active_stores as $store_id => $store) {
            $destination_id = $store['main_destination_id'];
            $manual_rate = Shippings::getRateByDestination($shipping_info, (int) $destination_id);

            $cannot_pickup_from_store = $manual_rate === false;
            if ($cannot_pickup_from_store) {
                continue;
            }
            $store['pickup_rate'] = $manual_rate;
            $store['delivery_time'] = $shipping_info['delivery_time'];
            if (!empty($destination_rates[$destination_id]['delivery_time'])) {
                $store['delivery_time'] = $destination_rates[$destination_id]['delivery_time'];
            }

            $stores[$store_id] = $store;
        }

        return fn_sort_array_by_key($stores, 'position');
    }
}
