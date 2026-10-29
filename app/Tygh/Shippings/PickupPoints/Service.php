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

use Tygh\Shippings\IPickupPointsService;
use Tygh\Shippings\PickupPoints\LoaderByLocation\ShippingMethodProvidedPickupPointsLoaderFactory;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\Api\IApiPickupPointsLoadingStrategy;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\Api\PickupPointsByCartLoader;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\Api\PickupPointsByOrderLoader;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\Checkout\ICheckoutPickupPointsLoadingStrategy;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\Checkout\SingleSelectedShippingMethodPickupPointsLoader;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\OrderManagement\IOrderManagementPickupPointsLoadingStrategy;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\Order\AllOrderShippingsPickupPointsLoader;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\Order\IOrderPickupPointsLoadingStrategy;
use Tygh\Shippings\PickupPoints\LoaderByUseCase\OrderManagement\SelectedShippingPickupPointsLoader;

/**
 * Pickup points service
 */
class Service
{
    /**
     * Checks if pickup points are supported by the shipping method settings.
     *
     * @param array $shipping_info Shipping method data
     *
     * @return bool
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     */
    public function isPickupPointsSupported(array $shipping_info)
    {
        /** @var string|null $shipping_module */
        $shipping_module = $shipping_info['module'] ?? null;

        if ($shipping_module === null) {
            return false;
        }

        $shipping_service_class = 'Tygh\\Shippings\\Services\\' . fn_camelize($shipping_module);

        if (!class_exists($shipping_service_class)) {
            return false;
        }

        if (!is_subclass_of($shipping_service_class, IPickupPointsService::class)) {
            return false;
        }

        /** @var \Tygh\Shippings\IPickupPointsService $shipping_service */
        $shipping_service = new $shipping_service_class();

        return $shipping_service->isPickupPointsSupported($shipping_info);
    }

    /**
     * @param array $cart Cart data
     *
     * @return array|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getPickupPointsForCheckout(array $cart)
    {
        $pickup_points = null;

        /**
         * Executes before fetching pickup points for checkout.
         * Allows preventing following script by setting your own list of pickup points.
         *
         * @param array $cart          Cart data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_checkout_pre', $cart, $pickup_points);

        if ($pickup_points !== null && is_array($pickup_points)) {
            return $pickup_points;
        }

        $loader = $this->getCheckoutPickupPointsLoader($cart);

        if ($loader === null) {
            return $pickup_points;
        }

        $pickup_points = $loader->getPickupPoints();

        /**
         * Executes after fetching pickup points for checkout.
         * Allows changing data in pickup points list.
         *
         * @param array $cart          Cart data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_checkout_post', $cart, $pickup_points);

        return $pickup_points;
    }

    /**
     * Synchronizes the checkout cart pickup point selection with already loaded pickup points.
     *
     * When a selected shipping method has a single available pickup point, stores it in the cart and marks shipping
     * rates for recalculation. The following checkout recalculation lets the shipping service save the selected pickup
     * point data to shipping extra data.
     *
     * @param array $cart          Cart data
     * @param array $pickup_points Pickup points indexed by product group and shipping method
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     */
    public function actualizePickupPointSelectionForCheckout(array &$cart, array $pickup_points)
    {
        foreach ($pickup_points as $group_key => $shippings_pickup_points) {
            foreach ($shippings_pickup_points as $shipping_id => $shipping_pickup_points) {
                if (
                    count($shipping_pickup_points) !== 1
                    || empty($cart['chosen_shipping'][$group_key])
                    || (int) $cart['chosen_shipping'][$group_key] !== (int) $shipping_id
                    || !empty($cart['select_pickup_point'][$group_key][$shipping_id])
                ) {
                    continue;
                }

                $pickup_point = reset($shipping_pickup_points);
                $cart['select_pickup_point'][$group_key][$shipping_id] = $pickup_point->getPickupPointCode();
                $cart['calculate_shipping'] = true;
            }
        }
    }

    /**
     * @param array $cart Cart data
     *
     * @return ICheckoutPickupPointsLoadingStrategy|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getCheckoutPickupPointsLoader(array $cart)
    {
        $loader = null;

        /**
         * Executes before getting pickup points loader for checkout.
         * Allows setting your own loader.
         *
         * @param array $cart   Cart data
         * @param array $loader Pickup points list
         */
        fn_set_hook('get_checkout_pickup_points_loader_pre', $cart, $loader);

        if ($loader instanceof ICheckoutPickupPointsLoadingStrategy) {
            return $loader;
        }

        /* TODO: Use AllAvailableShippingMethodsPickupPointsLoader to show all pickup points (for example, on one map)
        $load_all_pickup_points = false;

        if ($load_all_pickup_points) {
            $loader = new AllAvailableShippingMethodsPickupPointsLoader(
                new ShippingMethodProvidedPickupPointsLoaderFactory(),
                $cart
            );
        } else {*/
            $loader = new SingleSelectedShippingMethodPickupPointsLoader(
                new ShippingMethodProvidedPickupPointsLoaderFactory(),
                $cart
            );
        /*}*/

        /**
         * Executes after getting pickup points loader for checkout.
         * Allows changing loader data.
         *
         * @param array $cart   Cart data
         * @param array $loader Pickup points list
         */
        fn_set_hook('get_checkout_pickup_points_loader_post', $cart, $loader);

        return $loader;
    }

    /**
     * @param array $cart Cart data
     *
     * @return array|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getPickupPointsForOrderManagement(array $cart)
    {
        $pickup_points = null;

        /**
         * Executes before fetching pickup points for order management.
         * Allows preventing following script by setting your own list of pickup points.
         *
         * @param array $cart          Cart data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_order_management_pre', $cart, $pickup_points);

        if ($pickup_points !== null && is_array($pickup_points)) {
            return $pickup_points;
        }

        $loader = $this->getOrderManagementPickupPointsLoader($cart);

        if ($loader === null) {
            return $pickup_points;
        }

        $pickup_points = $loader->getPickupPoints();

        /**
         * Executes after fetching pickup points for order management.
         * Allows changing data in pickup points list.
         *
         * @param array $cart          Cart data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_order_management_post', $cart, $pickup_points);

        return $pickup_points;
    }

    /**
     * @param array $cart Cart data
     *
     * @return IOrderManagementPickupPointsLoadingStrategy|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getOrderManagementPickupPointsLoader(array $cart)
    {
        $loader = null;

        /**
         * Executes before getting pickup points loader for order management.
         * Allows setting your own loader.
         *
         * @param array $cart   Cart data
         * @param array $loader Pickup points list
         */
        fn_set_hook('get_order_management_pickup_points_loader_pre', $cart, $loader);

        if ($loader instanceof IOrderManagementPickupPointsLoadingStrategy) {
            return $loader;
        }

        $loader = new SelectedShippingPickupPointsLoader(
            new ShippingMethodProvidedPickupPointsLoaderFactory(),
            $cart
        );

        /**
         * Executes after getting pickup points loader for order management.
         * Allows changing loader data.
         *
         * @param array $cart   Cart data
         * @param array $loader Pickup points list
         */
        fn_set_hook('get_order_management_pickup_points_loader_post', $cart, $loader);

        return $loader;
    }

    /**
     * @param array $order_info Order data
     *
     * @return array|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getPickupPointsForOrder(array $order_info)
    {
        $pickup_points = null;

        /**
         * Executes before fetching pickup points for order.
         * Allows preventing following script by setting your own list of pickup points.
         *
         * @param array $order_info    Order data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_order_pre', $order_info, $pickup_points);

        if ($pickup_points !== null && is_array($pickup_points)) {
            return $pickup_points;
        }

        $loader = $this->getOrderPickupPointsLoader($order_info);

        if ($loader === null) {
            return $pickup_points;
        }

        $pickup_points = $loader->getPickupPoints();

        /**
         * Executes after fetching pickup points for order.
         * Allows changing data in pickup points list.
         *
         * @param array $order_info    Order data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_order_post', $order_info, $pickup_points);

        return $pickup_points;
    }

    /**
     * @param array $order_info Order data
     *
     * @return IOrderPickupPointsLoadingStrategy|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getOrderPickupPointsLoader(array $order_info)
    {
        $loader = null;

        /**
         * Executes before getting pickup points loader for order.
         * Allows setting your own loader.
         *
         * @param array $order_info Order data
         * @param array $loader     Pickup points list
         */
        fn_set_hook('get_order_pickup_points_loader_pre', $order_info, $loader);

        if ($loader instanceof IOrderPickupPointsLoadingStrategy) {
            return $loader;
        }

        $loader = new AllOrderShippingsPickupPointsLoader(
            new ShippingMethodProvidedPickupPointsLoaderFactory(),
            $order_info
        );

        /**
         * Executes after getting pickup points loader for order.
         * Allows changing loader data.
         *
         * @param array $order_info Order data
         * @param array $loader     Pickup points list
         */
        fn_set_hook('get_order_pickup_points_loader_post', $order_info, $loader);

        return $loader;
    }

    /**
     * @param array                         $cart                   Cart data
     * @param bool                          $selected_shipping_only Whether pickup points only for selected shippings needed
     * @param array<int|string, int|string> $requested_shipping_ids Methods the client asked about, by group
     *
     * @return array|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getPickupPointsForApiByCart(array $cart, $selected_shipping_only = false, array $requested_shipping_ids = [])
    {
        $pickup_points = null;

        /**
         * Executes before fetching pickup points for API by cart.
         * Allows preventing following script by setting your own list of pickup points.
         *
         * @param array $cart                   Cart data
         * @param bool  $selected_shipping_only Whether pickup points only for selected shippings needed
         * @param array $pickup_points          Pickup points list
         */
        fn_set_hook('get_pickup_points_for_api_by_cart_pre', $cart, $selected_shipping_only, $pickup_points);

        if ($pickup_points !== null && is_array($pickup_points)) {
            return $pickup_points;
        }

        $loader = $this->getPickupPointsForApiByCartLoader($cart, $selected_shipping_only, $requested_shipping_ids);

        if ($loader === null) {
            return $pickup_points;
        }

        $pickup_points = $loader->getPickupPoints();

        /**
         * Executes after fetching pickup points for API by cart.
         * Allows changing data in pickup points list.
         *
         * @param array $cart                   Cart data
         * @param bool  $selected_shipping_only Whether pickup points only for selected shippings needed
         * @param array $pickup_points          Pickup points list
         */
        fn_set_hook('get_pickup_points_for_api_by_cart_post', $cart, $selected_shipping_only, $pickup_points);

        return $pickup_points;
    }

    /**
     * @param array                         $cart                   Cart data
     * @param bool                          $selected_shipping_only Whether pickup points only for selected shippings needed
     * @param array<int|string, int|string> $requested_shipping_ids Methods the client asked about, by group
     *
     * @return IApiPickupPointsLoadingStrategy|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getPickupPointsForApiByCartLoader(array $cart, $selected_shipping_only = false, array $requested_shipping_ids = [])
    {
        $loader = null;

        /**
         * Executes before getting pickup points loader for API by cart.
         * Allows setting your own loader.
         *
         * @param array $cart                   Cart data
         * @param bool  $selected_shipping_only Whether pickup points only for selected shippings needed
         * @param array $loader                 Loader
         */
        fn_set_hook('get_pickup_points_for_api_by_cart_loader_pre', $cart, $loader);

        if ($loader instanceof IApiPickupPointsLoadingStrategy) {
            return $loader;
        }

        $loader = new PickupPointsByCartLoader(
            new ShippingMethodProvidedPickupPointsLoaderFactory(),
            $cart,
            $selected_shipping_only,
            $requested_shipping_ids
        );

        /**
         * Executes after getting pickup points loader for API by cart.
         * Allows changing loader data.
         *
         * @param array $cart                   Cart data
         * @param bool  $selected_shipping_only Whether pickup points only for selected shippings needed
         * @param array $loader                 Loader
         */
        fn_set_hook('get_pickup_points_for_api_by_cart_loader_post', $cart, $loader);

        return $loader;
    }

    /**
     * @param array $order_info Order data
     *
     * @return array|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getPickupPointsForApiByOrder(array $order_info)
    {
        $pickup_points = null;

        /**
         * Executes before fetching pickup points for API by order.
         * Allows preventing following script by setting your own list of pickup points.
         *
         * @param array $order_info    Order data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_api_by_order_pre', $order_info, $pickup_points);

        if ($pickup_points !== null && is_array($pickup_points)) {
            return $pickup_points;
        }

        $loader = $this->getPickupPointsForApiByOrderLoader($order_info);

        if ($loader === null) {
            return $pickup_points;
        }

        $pickup_points = $loader->getPickupPoints();

        /**
         * Executes after fetching pickup points for API by order.
         * Allows changing data in pickup points list.
         *
         * @param array $order_info    Order data
         * @param array $pickup_points Pickup points list
         */
        fn_set_hook('get_pickup_points_for_api_by_order_post', $order_info, $pickup_points);

        return $pickup_points;
    }

    /**
     * @param array $order_info Order data
     *
     * @return IApiPickupPointsLoadingStrategy|null
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getPickupPointsForApiByOrderLoader(array $order_info)
    {
        $loader = null;

        /**
         * Executes before getting pickup points loader for API by order.
         * Allows setting your own loader.
         *
         * @param array $order_info Order data
         * @param array $loader     Loader
         */
        fn_set_hook('get_pickup_points_for_api_by_order_loader_pre', $order_info, $loader);

        if ($loader instanceof IApiPickupPointsLoadingStrategy) {
            return $loader;
        }

        $loader = new PickupPointsByOrderLoader(
            new ShippingMethodProvidedPickupPointsLoaderFactory(),
            $order_info
        );

        /**
         * Executes after getting pickup points loader for API by cart.
         * Allows changing loader data.
         *
         * @param array $order_info Order data
         * @param array $loader     Loader
         */
        fn_set_hook('get_pickup_points_for_api_by_order_loader_post', $order_info, $loader);

        return $loader;
    }
}
