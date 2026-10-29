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

namespace Tygh\Addons\YandexDelivery\HookHandlers;

use Tygh\Addons\YandexDelivery\Enum\DeliveryType;
use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Addons\YandexDelivery\Tools\PickupPointSelection;
use Tygh\Enum\NotificationSeverity;

/**
 * Saves and validates the selected Yandex Delivery pickup point in cart data.
 */
class CartsHookHandler
{
    /** @var ShippingDataBuilder */
    protected $shipping_data_builder;

    /**
     * @param ShippingDataBuilder $shipping_data_builder Reads the shipping method setup
     *
     * @return void
     */
    public function __construct(ShippingDataBuilder $shipping_data_builder)
    {
        $this->shipping_data_builder = $shipping_data_builder;
    }

    /**
     * Saves the pickup point selected on checkout into the cart.
     *
     * @param array<string, array<int|string, array<int|string, string|int>>|string|int|bool|null> $cart          Cart content
     * @param array{select_pickup_point?: array<int|string, array<int|string, string|int>>}        $new_cart_data New cart data
     * @param array<string, string|int|bool|null>                                                  $auth          Auth data
     *
     * @return void
     */
    public function onUpdateCartByDataPost(array &$cart, array $new_cart_data, array $auth)
    {
        if (empty($new_cart_data['select_pickup_point'])) {
            return;
        }

        $cart['select_pickup_point'] = $new_cart_data['select_pickup_point'];
    }

    /**
     * Adds selected pickup point data and calculated Yandex Delivery data to product groups.
     *
     * @param array $cart           Cart content
     * @param array $cart_products  Cart products data
     * @param array $product_groups Product groups data
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function onCalculateCartTaxesPre(array &$cart, array $cart_products, array &$product_groups)
    {
        if (empty($cart['shippings_extra']['data'])) {
            return;
        }

        $selected_pickup_points = [];
        if (!empty($cart['select_pickup_point'])) {
            $selected_pickup_points = $cart['select_pickup_point'];
        } elseif (!empty($_REQUEST['select_pickup_point'])) {
            $selected_pickup_points = PickupPointSelection::sanitize($_REQUEST['select_pickup_point']);
        }

        $cart['select_pickup_point'] = $selected_pickup_points;

        foreach ($product_groups as $group_key => $group) {
            if (empty($group['chosen_shippings'])) {
                continue;
            }

            foreach ($group['chosen_shippings'] as $shipping_key => $shipping) {
                $shipping_id = $shipping['shipping_id'];

                if (
                    ($shipping['module'] ?? '') !== 'yandex_delivery'
                    || empty($cart['shippings_extra']['data'][$group_key][$shipping_id])
                ) {
                    continue;
                }

                $shipping_extra = $cart['shippings_extra']['data'][$group_key][$shipping_id];
                $product_groups[$group_key]['chosen_shippings'][$shipping_key]['data'] = $shipping_extra;

                if (empty($selected_pickup_points[$group_key][$shipping_id])) {
                    continue;
                }

                $pickup_point_id = (string) $selected_pickup_points[$group_key][$shipping_id];
                $product_groups[$group_key]['chosen_shippings'][$shipping_key]['pickup_point_id'] = $pickup_point_id;

                if (!$this->isSelectedPickupPointData($shipping_extra['selected_pickup_point_data'] ?? [], $pickup_point_id)) {
                    continue;
                }

                $product_groups[$group_key]['chosen_shippings'][$shipping_key]['pickup_point_data']
                    = $shipping_extra['selected_pickup_point_data'];
            }
        }

        foreach ($cart['shippings_extra']['data'] as $group_key => $shippings) {
            foreach ($shippings as $shipping_id => $shipping_extra) {
                if (($product_groups[$group_key]['shippings'][$shipping_id]['module'] ?? '') !== 'yandex_delivery') {
                    continue;
                }

                $product_groups[$group_key]['shippings'][$shipping_id]['data'] = $shipping_extra;

                if (!empty($shipping_extra['delivery_time'])) {
                    $product_groups[$group_key]['shippings'][$shipping_id]['delivery_time'] = $shipping_extra['delivery_time'];
                }
            }
        }
    }

    /**
     * Prevents order placement when a Yandex Delivery pickup method has no selected pickup point.
     *
     * @param array $cart           Cart content
     * @param bool  $allow          Whether order placement is allowed
     * @param array $product_groups Product groups data
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function onPrePlaceOrder(array &$cart, &$allow, array $product_groups)
    {
        $selected_pickup_points = $cart['select_pickup_point'] ?? [];
        if (empty($selected_pickup_points) && !empty($_REQUEST['select_pickup_point'])) {
            $selected_pickup_points = PickupPointSelection::sanitize($_REQUEST['select_pickup_point']);
        }

        foreach ($product_groups as $group_key => $group) {
            if (empty($group['chosen_shippings']) || !is_array($group['chosen_shippings'])) {
                continue;
            }

            foreach ($group['chosen_shippings'] as $shipping) {
                if (
                    ($shipping['module'] ?? '') !== 'yandex_delivery'
                    || !DeliveryType::isPickup($this->shipping_data_builder->getDeliveryType($shipping['service_params'] ?? []))
                ) {
                    continue;
                }

                $shipping_id = isset($shipping['shipping_id']) ? (int) $shipping['shipping_id'] : 0;
                $selected_point = $shipping_id === 0
                    ? ''
                    : trim((string) ($selected_pickup_points[$group_key][$shipping_id] ?? ''));

                // The storefront keeps posting a point the calculation already refused, so the refusal is
                // checked here too: without it an order Yandex will not carry gets placed through that point.
                $refused_point = trim((string) (
                    $cart['shippings_extra']['data'][$group_key][$shipping_id]['refused_pickup_point'] ?? ''
                ));

                // The storefront keeps posting the choice even after a calculation dropped it, so what the
                // calculation left behind decides, not the form: with no point taken the order waits.
                $point_still_needed = !empty(
                    $cart['shippings_extra']['data'][$group_key][$shipping_id]['pickup_point_required']
                );

                if (!$point_still_needed && $selected_point !== '' && $selected_point !== $refused_point) {
                    continue;
                }

                $allow = false;
                fn_set_notification(
                    NotificationSeverity::ERROR,
                    __('error'),
                    $point_still_needed || $selected_point === ''
                        ? __('pickup_point_not_selected')
                        : __('yandex_delivery.selected_pickup_point_unavailable')
                );

                return;
            }
        }
    }

    /**
     * The "place_suborders_pre" hook handler.
     *
     * Actions performed:
     * - Generates the correct keys of "shippings_extra" and "select_pickup_point" in suborder cart.
     *
     * @param int                                                                                                                         $order_id      Order identifier
     * @param array<string|int>                                                                                                           $cart          Cart contents
     * @param array<string|int>                                                                                                           $auth          Authentication data
     * @param string                                                                                                                      $action        Current action. Can be empty or "save"
     * @param int                                                                                                                         $issuer_id     Issuer identifier
     * @param array{shippings_extra?: array{data?: array<string|int>}, chosen_shipping?: array<int>, shipping?: array<array<string|int>>} $suborder_cart Child cart contents
     * @param int                                                                                                                         $key_group     Child cart products group key
     * @param array<string|int>                                                                                                           $group         Child cart products
     *
     * @see \fn_place_suborders()
     *
     * @return void
     */
    public function onPlaceSubordersPre($order_id, $cart, $auth, $action, $issuer_id, &$suborder_cart, $key_group, $group)
    {
        $chosen_shipping_id = $suborder_cart['chosen_shipping'][$key_group] ?? null;
        if ($chosen_shipping_id === null) {
            return;
        }

        $shipping = $suborder_cart['shipping'][$chosen_shipping_id] ?? [];
        if (($shipping['module'] ?? '') !== 'yandex_delivery') {
            return;
        }

        if (isset($suborder_cart['shippings_extra']['data'][$key_group])) {
            $suborder_cart['shippings_extra']['data'] = [$suborder_cart['shippings_extra']['data'][$key_group]];
        }

        if (!isset($suborder_cart['select_pickup_point'][$key_group])) {
            return;
        }

        $suborder_cart['select_pickup_point'] = [$suborder_cart['select_pickup_point'][$key_group]];
    }

    /**
     * The "order_fulfillment_set_marketplace_shipping_to_product_group" hook handler.
     *
     * Actions performed:
     * - Copies the pickup point chosen for the marketplace group into the group the shipping is applied to.
     *
     * @param array<string, array<int|string, array<int|string, string|int>>> $cart                  Cart contents
     * @param int|string                                                      $group_key             Product group key
     * @param int|string                                                      $marketplace_group_key Marketplace group key
     * @param array<string, string|int|float|bool|array|null>                 $chosen_shipping_data  Chosen shipping
     *
     * @see \Tygh\Addons\OrderFulfillment\HookHandlers\OrdersHookHandler::onPrePlaceOrder()
     *
     * @return void
     */
    public function onOrderFulfillmentSetMarketplaceShippingToProductGroup(
        array &$cart,
        $group_key,
        $marketplace_group_key,
        array $chosen_shipping_data
    ) {
        if (empty($cart['select_pickup_point'][$marketplace_group_key])) {
            return;
        }

        $cart['select_pickup_point'][$group_key] = $cart['select_pickup_point'][$marketplace_group_key];
    }

    /**
     * Checks whether stored pickup point data matches the selected pickup point.
     *
     * @param array<string, string|int|float|bool|array|null> $pickup_point_data Pickup point data
     * @param string                                          $pickup_point_id   Selected pickup point ID
     *
     * @return bool
     */
    protected function isSelectedPickupPointData(array $pickup_point_data, $pickup_point_id)
    {
        foreach (['code', 'id', 'platform_station_id'] as $field) {
            if (ExternalValue::text($pickup_point_data, $field) === $pickup_point_id) {
                return true;
            }
        }

        return false;
    }
}
