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

namespace Tygh\Shippings\PickupPoints\LoaderByUseCase\Api;

use Tygh\Registry;
use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Shippings\PickupPoints\LoaderByLocation\ShippingMethodProvidedPickupPointsLoaderFactory;

class PickupPointsByCartLoader implements IApiPickupPointsLoadingStrategy
{
    /**
     * @var ShippingMethodProvidedPickupPointsLoaderFactory
     */
    protected $factory;

    //phpcs:ignore
    /** @var array */
    protected $cart;

    /** @var bool $selected_shipping_only Whether pickup points only for selected shippings needed */
    protected $selected_shipping_only = false;

    /**
     * Shipping methods the client asked about, by product group: the choice it has already made while the
     * saved cart still holds the previous one.
     *
     * Keyed by `group_key`, the name of the group the client is given and answered with. The position a
     * group happens to occupy in the cart is a different number and names nothing outside the cart.
     *
     * @var array<int, int>
     */
    protected $requested_shipping_ids = [];

    /**
     * @param ShippingMethodProvidedPickupPointsLoaderFactory $factory                Pickup points loader factory
     * @param array                                           $cart                   Cart data
     * @param bool                                            $selected_shipping_only Whether pickup points only for selected shippings needed
     * @param array<int|string, int|string>                   $requested_shipping_ids Methods the client asked about, by group
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function __construct(
        ShippingMethodProvidedPickupPointsLoaderFactory $factory,
        array $cart,
        $selected_shipping_only = false,
        array $requested_shipping_ids = []
    ) {
        $this->factory = $factory;
        $this->cart = $cart;
        $this->selected_shipping_only = $selected_shipping_only;

        // Normalized on the way in: this is a public boundary, and the group keys have to compare by identity
        // against the `group_key` of the cart product groups.
        foreach ($requested_shipping_ids as $group_key => $shipping_id) {
            $this->requested_shipping_ids[(int) $group_key] = (int) $shipping_id;
        }
    }

    /**
     * Names the shipping methods pickup points are loaded for in one product group.
     *
     * Kept in one place so there is a single answer to "which method": every method of the group when the
     * caller wants them all, otherwise the one the client asked about, and failing that the one the saved
     * cart holds. An unsaved choice wins over a saved one; a method the group does not offer is ignored.
     *
     * @param int|string                                      $group_key     Position of the group in the cart
     * @param array<string, string|int|float|bool|array|null> $product_group Product group data
     *
     * @return array<int, int>
     */
    protected function getShippingMethodsForGroup($group_key, array $product_group)
    {
        $offered = $product_group['shippings'] ?? [];
        $offered = is_array($offered) ? $offered : [];

        if (!$this->selected_shipping_only) {
            return array_map('intval', array_keys($offered));
        }

        // The client names the group the way the answer names it, by `group_key`; the saved choice is kept
        // by the group position instead, so the two are looked up by their own keys.
        $group_name = $product_group['group_key'] ?? null;
        $requested = is_numeric($group_name)
            ? $this->requested_shipping_ids[(int) $group_name] ?? null
            : null;

        if ($requested !== null && isset($offered[$requested])) {
            return [$requested];
        }

        return isset($this->cart['chosen_shipping'][$group_key])
            ? [(int) $this->cart['chosen_shipping'][$group_key]]
            : [];
    }

    /**
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function getPickupPoints()
    {
        $result = [];

        if (!isset($this->cart['product_groups'])) {
            return $result;
        }

        foreach ($this->cart['product_groups'] as $group_key => $product_group) {
            $shipping_methods = $this->getShippingMethodsForGroup($group_key, $product_group);

            if (empty($shipping_methods)) {
                continue;
            }

            if (fn_allowed_for('MULTIVENDOR')) {
                $company_id = $product_group['company_id'] ?? Registry::get('runtime.company_id');
            } else {
                $company_id = Registry::get('runtime.company_id');
            }

            foreach ($shipping_methods as $shipping_id) {
                if (empty($product_group['package_info'])) {
                    continue;
                }

                $lookup_context = PickupPointsLookupContext::fromPackageInfo($shipping_id, $company_id, $product_group['package_info']);

                $shipping_provided_loader = $this->factory->getShippingMethodProvidedPickupPointsLoader($shipping_id);

                if ($shipping_provided_loader === null) {
                    continue;
                }

                $pickup_points = $shipping_provided_loader->getPickupPointsByLocation($lookup_context);

                $pickup_points_array = [];
                foreach ($pickup_points as $pickup_point) {
                    $pickup_point_array = $pickup_point->toArray();
                    unset($pickup_point_array['extra_data']);
                    $pickup_points_array[] = $pickup_point_array;
                }

                $result[] = [
                    'group_key'     => (int) ($product_group['group_key'] ?? 0),
                    'shipping_id'   => $shipping_id,
                    'pickup_points' => $pickup_points_array,
                ];
            }
        }

        return $result;
    }
}
