{if $cart.chosen_shipping.$group_key|intval === $shipping.shipping_id|intval && $shipping.module === "yandex_delivery" && $shipping.service_params.delivery_type === "self_pickup"}
    {$shipping_id = $shipping.shipping_id}

    {if $pickup_points.$group_key.$shipping_id|count >= 1}
        {script src="js/addons/yandex_delivery/map.js"}

        {$old_pickup_point_code = $select_pickup_point.$group_key.$shipping_id}
        {$store_count = $pickup_points.$group_key.$shipping_id|count}

        <h2 class="litecheckout__step-title">{__("lite_checkout.select_pickup_item")}</h2>

        {hook name="checkout:yandex_delivery_pickup_content"}
            {include file="views/checkout/components/shippings/list.tpl" old_pickup_point_code=$old_pickup_point_code shipping_tag="yandex-delivery"}
        {/hook}
    {/if}
{/if}
