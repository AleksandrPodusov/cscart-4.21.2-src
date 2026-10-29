{foreach from=$cart.shipping item=sdek_shipping}
    {if $sdek_shipping.module === 'store_locator'}

        {if $product_groups}
            {foreach from=$product_groups key=group_key item=group}
                {if $group.shippings && !$group.shipping_no_required}

                    {foreach from=$group.shippings item=shipping}
                        {if $cart.chosen_shipping.$group_key == $shipping.shipping_id}

                            {if $pickup_points}

                                {$old_store_id = $old_ship_data.$group_key.store_location_id}
                                {$shipping_id = $shipping.shipping_id}
                                {$select_id = $select_pickup_point.$group_key.$shipping_id}
                                {$store_count = $pickup_points.$group_key.$shipping_id|count}

                                {if $store_count === 1}
                                    {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
                                        {$pickup_point_extra_data = $pickup_point.extra_data}
                                        <div class="sidebar-row">
                                            <input type="hidden"
                                                name="select_pickup_point[{$group_key}][{$shipping_id}]"
                                                value="{$pickup_point.pickup_point_code}"
                                                id="store_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}"
                                                class="cm-submit cm-ajax cm-skip-validation"
                                                data-ca-dispatch="dispatch[order_management.update_shipping]"
                                                data-ca-pickup-select-store="true"
                                                data-ca-shipping-id="{$shipping_id}"
                                                data-ca-group-key="{$group_key}"
                                                data-ca-location-id="{$pickup_point.pickup_point_code}">
                                            {$pickup_point_extra_data.name} {if $pickup_point_extra_data.pickup_rate}({include file="common/price.tpl" value=$pickup_point_extra_data.pickup_rate}){/if}
                                            <p class="muted">
                                            {$pickup_point_extra_data.city}, {$pickup_point_extra_data.pickup_address},
                                            {$pickup_point_extra_data.pickup_phone}<br/>
                                            {__("store_locator.work_time")}: {$pickup_point_extra_data.pickup_time}
                                            <br/>
                                            {if $pickup_point_extra_data.delivery_time}{__("delivery_time")}: {$pickup_point_extra_data.delivery_time}{/if}
                                            </p>
                                        </div>
                                    {/foreach}
                                {else}
                                    {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
                                        {$pickup_point_extra_data = $pickup_point.extra_data}
                                        <div class="sidebar-row">
                                            <div class="control-group">
                                                <div id="pickup_stores" class="controls">
                                                    <label for="store_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}" class="radio">
                                                        <input type="radio"
                                                            name="select_pickup_point[{$group_key}][{$shipping_id}]"
                                                            value="{$pickup_point.pickup_point_code}"
                                                            {if $select_id == $pickup_point.pickup_point_code || (!$select_id && $old_store_id == $pickup_point.pickup_point_code)}checked="checked"{/if}
                                                            id="store_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}"
                                                            class="cm-submit cm-ajax cm-skip-validation"
                                                            data-ca-dispatch="dispatch[order_management.update_shipping]"
                                                            data-ca-pickup-select-store="true"
                                                            data-ca-shipping-id="{$shipping_id}"
                                                            data-ca-group-key="{$group_key}"
                                                            data-ca-location-id="{$pickup_point.pickup_point_code}">
                                                            {$pickup_point_extra_data.name} {if $pickup_point_extra_data.pickup_rate}({include file="common/price.tpl" value=$pickup_point_extra_data.pickup_rate}){/if}
                                                    </label>
                                                    <p class="muted">
                                                        {$pickup_point.city_name}, {$pickup_point.address},
                                                        {$pickup_point.phone}<br/>
                                                        {__("store_locator.work_time")}: {$pickup_point.work_time}
                                                        <br/>
                                                        {if $pickup_point_extra_data.delivery_time}{__("delivery_time")}: {$pickup_point_extra_data.delivery_time}{/if}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    {/foreach}
                                {/if}
                            {/if}
                        {/if}
                    {/foreach}
                {/if}
            {/foreach}
        {/if}
    {/if}
{/foreach}