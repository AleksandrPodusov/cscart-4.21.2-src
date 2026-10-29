{if $cart.chosen_shipping.$group_key|intval === $shipping.shipping_id|intval && $shipping.module === "sdek2"}

    {$shipping_id = $shipping.shipping_id}

    {if $pickup_points.$group_key.$shipping_id|count >= 1}
        {script src="js/addons/rus_sdek2/map.js"}

        {$old_office_id = $select_pickup_point.$group_key.$shipping_id}

        {$store_count = $pickup_points.$group_key.$shipping_id|count}

        <h2 class="litecheckout__step-title">{__("lite_checkout.select_pickup_item")}</h2>

        {hook name="checkout:rus_sdek_pickup_content"}
            {include file="views/checkout/components/shippings/list.tpl" old_pickup_point_code=$old_office_id shipping_tag="sdek"}
        {/hook}
    {/if}
{/if}
