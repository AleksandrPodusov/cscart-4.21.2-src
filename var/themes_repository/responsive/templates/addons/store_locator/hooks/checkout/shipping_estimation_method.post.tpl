{if $shipping.module === "store_locator"}
    <div class="clearfix" id="rate_extra_{$shipping_estimation_block_id}_{$group_key}_{$shipping.shipping_id}{$id_suffix}">
    {if $cart.chosen_shipping.$group_key == $shipping.shipping_id}
        {$shipping_id = $shipping.shipping_id}
        {$store_count = $pickup_points.$group_key.$shipping_id|default:[]|count}

        {if $store_count >= 1}
            <div class="ty-checkout-select-store__estimation">
                {$old_store_id = $select_pickup_point.$group_key.$shipping_id}

                {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
                    {$pickup_point_extra_data = $pickup_point.extra_data}

                    <div class="ty-one-store">
                        <input type="radio"
                            name="select_pickup_point[{$group_key}][{$shipping.shipping_id}]"
                            value="{$pickup_point.pickup_point_code}"
                            {if $old_store_id === $pickup_point.pickup_point_code || $store_count === 1}checked="checked"{/if}
                            id="store_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}"
                            class="ty-one-store__radio ty-valign"
                            onclick="fn_calculate_total_shipping();"
                            data-ca-pickup-select-store="true"
                            data-ca-shipping-id="{$shipping_id}"
                            data-ca-group-key="{$group_key}"
                            data-ca-location-id="{$pickup_point.pickup_point_code}"
                        />
                        <label for="store_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}" class="ty-valign">
                            <span class="ty-one-store__name">
                                {if $pickup_point.pickup_point_name}
                                    {$pickup_point.pickup_point_name}
                                {else}
                                    {$pickup_point.pickup_point_code}
                                {/if}
                                {if $pickup_point_extra_data.pickup_rate}
                                    ({include file="common/price.tpl" value=$pickup_point_extra_data.pickup_rate})
                                {/if}
                            </span>
                            {if $pickup_point.address}
                                <span class="muted">
                                    <br/>{$pickup_point.address}
                                </span>
                            {/if}
                            {if $pickup_point_extra_data.delivery_time}
                                <span class="muted">
                                    <br/>{__("delivery_time")}: {$pickup_point_extra_data.delivery_time}
                                </span>
                            {/if}
                        </label>
                    </div>
                {/foreach}
            </div>
        {/if}
    {/if}
    <!--rate_extra_{$shipping_estimation_block_id}_{$group_key}_{$shipping.shipping_id}{$id_suffix}--></div>
{/if}

