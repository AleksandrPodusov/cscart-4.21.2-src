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

namespace Tygh\Shippings\Services;

use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Shippings\PickupPoints\StoreLocatorPickupPointsByLocationLoader;
use Tygh\Enum\SiteArea;
use Tygh\Shippings\IPickupPointsService;
use Tygh\Shippings\IService;
use Tygh\Registry;
use Tygh\Tygh;

class StoreLocator implements IService, IPickupPointsService
{
    /**
     * Availability multithreading in this module
     *
     * @var bool $_allow_multithreading
     */
    private $allow_multithreading = false;

    /**
     * @var array $shipping_info Shipping information
     */
    private $shipping_info;

    /**
     * Current Company id environment
     *
     * @var int $company_id
     */
    public $company_id = 0;

    /**
     * Checks if shipping service allows to use multithreading
     *
     * @return bool true if allow
     */
    public function allowMultithreading()
    {
        return $this->allow_multithreading;
    }

    public function processErrors($response) {}

    /**
     * @inheritdoc
     */
    public function getPickupPointsLoader()
    {
        return new StoreLocatorPickupPointsByLocationLoader();
    }

    /**
     * @inheritdoc
     */
    public function isPickupPointsSupported(array $shipping_info)
    {
        return true;
    }

    /**
     * Sets data to internal class variable
     *
     * @param  array $shipping_info
     *
     * @return array|void
     */
    public function prepareData($shipping_info)
    {
        $this->shipping_info = $shipping_info;

        if (fn_allowed_for('MULTIVENDOR')) {
            if (SiteArea::isAdmin(AREA) && isset($shipping_info['company_id'])) {
                $this->company_id = $shipping_info['company_id'];
            } else {
                $this->company_id = isset($shipping_info['keys']['company_id'])
                    ? $shipping_info['keys']['company_id']
                    : Registry::get('runtime.company_id');
            }
        } else {
            $this->company_id = Registry::get('runtime.company_id');
        }
    }

    /**
     * Prepare request information
     *
     * @return array Prepared data
     */
    public function getRequestData()
    {
        return [];
    }

    /**
     * Process simple request to shipping service server
     *
     * @return string Server response
     */
    public function getSimpleRates()
    {
        return '';
    }

    /**
     * Gets shipping cost and information about possible errors
     *
     * @param string $response Server response
     *
     * @return array Shipping cost and errors
     */
    public function processResponse($response)
    {
        $result = [
            'cost'           => false,
            'error'          => false,
            'delivery_time'  => false,
            'destination_id' => false,
        ];

        $context_for_pickup_points = PickupPointsLookupContext::fromPackageInfo(
            $this->shipping_info['shipping_id'],
            $this->company_id,
            $this->shipping_info['package_info']
        );
        $pickup_points_loader = $this->getPickupPointsLoader();
        $available_stores = $pickup_points_loader->getPickupPointsByLocation($context_for_pickup_points);

        if (empty($available_stores)) {
            $result['error'] = __('stores_nothing_found');
            return $result;
        }

        $result['cost'] = 0; // zero for now, manual rate amount will be added later

        /* @var \Tygh\Shippings\PickupPoints\Dto\PickupPoint|null $selected_store */
        $selected_store = $this->fetchSelectedStoreData($available_stores);

        $this->storeShippingData($selected_store, $available_stores);

        if (!empty($selected_store)) {
            $selected_store_as_array = $selected_store->getExtraData();
            $result['destination_id'] = $selected_store_as_array['main_destination_id'];
            $result['delivery_time'] = $selected_store_as_array['delivery_time'];
        } else {
            $available_store = reset($available_stores);
            $available_store_as_array = $available_store->getExtraData();
            $result['destination_id'] = $available_store_as_array['main_destination_id'];
        }

        if (empty($result['destination_id']) && !empty($this->shipping_info['rate_info']['destination_id'])) {
            $result['destination_id'] = $this->shipping_info['rate_info']['destination_id'];
        }

        return $result;
    }

    /**
     * Fetches selected store data
     *
     * @param \Tygh\Shippings\PickupPoints\Dto\PickupPoint[] $stores Available stores
     *
     * @return \Tygh\Shippings\PickupPoints\Dto\PickupPoint|null
     */
    protected function fetchSelectedStoreData(array $stores)
    {
        $store_id = $this->shipping_info['select_pickup_point'] ?? null;

        if (!$store_id) {
            return null;
        }

        foreach ($stores as $store) {
            if ($store->getPickupPointCode() === $store_id) {
                return $store;
            }
        }

        return null;
    }

    /**
     * Saves shipping data to session
     *
     * @param \Tygh\Shippings\PickupPoints\Dto\PickupPoint|null   $selected_store   Selected pickup point object
     * @param array<\Tygh\Shippings\PickupPoints\Dto\PickupPoint> $available_stores Pickup points list
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function storeShippingData($selected_store, array $available_stores = [])
    {
        if (!isset($this->shipping_info['keys']['group_key']) || empty($this->shipping_info['keys']['shipping_id'])) {
            return;
        }

        $group_key = $this->shipping_info['keys']['group_key'];
        $shipping_id = $this->shipping_info['keys']['shipping_id'];

        if (!empty($selected_store)) {
            $store_as_array = $selected_store->getExtraData();
            Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['selected_pickup_point_data'] = $store_as_array;
        } else {
            unset(Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['selected_pickup_point_data']);
            unset(Tygh::$app['session']['cart']['select_pickup_point'][$group_key][$shipping_id]);
        }

        Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['pickup_points_count'] = count($available_stores);

        $pickup_min_cost = null;
        $pickup_max_cost = null;
        foreach ($available_stores as $store) {
            $store_as_array = $store->getExtraData();
            $pickup_rate = $store_as_array['pickup_rate'] ?? null;

            if ($pickup_rate === null) {
                continue;
            }

            $pickup_min_cost = $pickup_min_cost === null || $pickup_min_cost > $pickup_rate ? $pickup_rate : $pickup_min_cost;
            $pickup_max_cost = $pickup_max_cost === null || $pickup_max_cost < $pickup_rate ? $pickup_rate : $pickup_max_cost;
        }

        Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['pickup_min_cost'] = $pickup_min_cost;
        Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['pickup_max_cost'] = $pickup_max_cost;
    }

    protected function getShippingIdentifiers()
    {
        return [
            isset($this->shipping_info['keys']['group_key']) ? $this->shipping_info['keys']['group_key'] : null,
            isset($this->shipping_info['keys']['shipping_id']) ? $this->shipping_info['keys']['shipping_id'] : null,
        ];
    }

    public function prepareAddress($address) {}

    /**
     * Returns shipping service information
     *
     * @return array information
     */
    public static function getInfo()
    {
        return array(
            'name'         => __('carrier_store_locator'),
            'tracking_url' => '#',
        );
    }

    /**
     * Fetches stored data from session
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getStoredShippingData()
    {
        $group_key = $this->shipping_info['keys']['group_key'] ?? null;
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? null;
        if (
            $group_key !== null && $shipping_id !== null
            && isset(Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id])
        ) {
            return Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id];
        }

        return [];
    }

    /**
     * @inheritdoc
     */
    public function getPickupMinCost()
    {
        $shipping_data = $this->getStoredShippingData();
        return $shipping_data['pickup_min_cost'] ?? false;
    }

    /**
     * @inheritdoc
     */
    public function getPickupPointsQuantity()
    {
        $shipping_data = $this->getStoredShippingData();
        return $shipping_data['pickup_points_count'] ?? false;
    }
}
