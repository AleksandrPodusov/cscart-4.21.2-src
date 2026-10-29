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

namespace Tygh\Shippings\PickupPoints\LoaderByUseCase\OrderManagement;

use Tygh\Registry;
use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Shippings\PickupPoints\LoaderByLocation\ShippingMethodProvidedPickupPointsLoaderFactory;

class SelectedShippingPickupPointsLoader implements IOrderManagementPickupPointsLoadingStrategy
{
    /**
     * @var ShippingMethodProvidedPickupPointsLoaderFactory
     */
    protected $factory;

    //phpcs:ignore
    /** @var array */
    protected $cart;

    /**
     * @param ShippingMethodProvidedPickupPointsLoaderFactory $factory Pickup points loader factory
     * @param array                                           $cart    Cart data
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function __construct(ShippingMethodProvidedPickupPointsLoaderFactory $factory, array $cart)
    {
        $this->factory = $factory;
        $this->cart = $cart;
    }

    /**
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function getPickupPoints()
    {
        $pickup_points = [];
        $lookup_context = null;

        if (!isset($this->cart['product_groups'])) {
            return $pickup_points;
        }

        foreach ($this->cart['product_groups'] as $group_key => $product_group) {
            if (!isset($this->cart['chosen_shipping'][$group_key])) {
                continue;
            }

            $shipping_id = $this->cart['chosen_shipping'][$group_key];
            if (fn_allowed_for('MULTIVENDOR')) {
                $company_id = $product_group['company_id'] ?? Registry::get('runtime.company_id');
            } else {
                $company_id = Registry::get('runtime.company_id');
            }

            if (!empty($product_group['package_info'])) {
                $lookup_context = PickupPointsLookupContext::fromPackageInfo($shipping_id, $company_id, $product_group['package_info']);
            }

            if ($lookup_context === null) {
                continue;
            }

            $shipping_provided_loader = $this->factory->getShippingMethodProvidedPickupPointsLoader($shipping_id);

            if ($shipping_provided_loader === null) {
                continue;
            }

            $pickup_points[$group_key][$shipping_id] = $shipping_provided_loader->getPickupPointsByLocation($lookup_context);
        }

        return $pickup_points;
    }
}
