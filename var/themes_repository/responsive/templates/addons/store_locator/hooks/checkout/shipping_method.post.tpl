{if $cart.chosen_shipping.$group_key == $shipping.shipping_id && $shipping.module == "store_locator"}

    {$shipping_id = $shipping.shipping_id}

    {if $pickup_points.$group_key.$shipping_id|count >= 1}
        {script src="js/addons/store_locator/pickup.js"}

        {$store_count = $pickup_points.$group_key.$shipping_id|count}
        {$old_store_id = $select_pickup_point.$group_key.$shipping_id}

        {if $shipping.service_params.display}
            {$display_type = $shipping.service_params.display}
        {else}
            {$display_type = "ML"}
        {/if}

        {if $display_type != "L"}
            {$display_pickup_map = true}
        {/if}

        <div class="litecheckout__item">
            <h2 class="litecheckout__step-title">{__("lite_checkout.select_pickup_item")}</h2>
        </div>

        {hook name="checkout:store_locator_pickup_content"}
            {include file="views/checkout/components/shippings/list.tpl" old_pickup_point_code=$old_store_id shipping_tag="sl-pickup"}
        {/hook}
    {/if}
{/if}
