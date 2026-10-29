{if $shipping.module === "yandex_delivery" && $shipping.service_params.delivery_type === "self_pickup" && !$shipping.rate_disabled}
    {$shipping_id = $shipping.shipping_id}
    {$selected_pickup_point = $select_pickup_point.$group_key.$shipping_id|default:$cart.select_pickup_point.$group_key.$shipping_id|default:""}
    {if !$selected_pickup_point}
        {$rate = __("lite_checkout.select_pickup_item") scope=parent}
    {/if}
{/if}
