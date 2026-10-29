{foreach from=$cart.shipping item=sdek_shipping}
    {if $sdek_shipping.module === 'sdek2'}
        {if $product_groups}
            {foreach from=$product_groups key=group_key item=group}
                {if $group.shippings && !$group.shipping_no_required}
                    {foreach from=$group.shippings item=shipping}
                        {if $cart.chosen_shipping.$group_key == $shipping.shipping_id}

                            {$old_office_id = $old_ship_data.$group_key.office_id}
                            {$select_id = $select_pickup_point.$group_key.$shipping_id}
                            {$shipping_id = $shipping.shipping_id}

                            {if $pickup_points.$group_key.$shipping_id}
                                {$office_count = $pickup_points.$group_key.$shipping_id|count}
                            
                                {if $office_count == 1}
                                    {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
                                    <div class="sidebar-row">
                                        <input type="hidden"
                                        name="select_pickup_point[{$group_key}][{$shipping_id}]"
                                        value="{$pickup_point.pickup_point_code}"
                                        id="office_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}"
                                        checked="checked"
                                        data-ca-pickup-select-office="true"
                                        data-ca-shipping-id="{$shipping_id}"
                                        data-ca-group-key="{$group_key}"
                                        data-ca-location-id="{$pickup_point.pickup_point_code}">
                                        {$pickup_point.pickup_point_code}{if $pickup_point.city_name}, {$pickup_point.city_name}{/if}
                                        <p class="muted">
                                            {$pickup_point.address}<br />
                                            {$pickup_point.work_time}<br />
                                            {$pickup_point.phone}<br />
                                            {$pickup_point.notes}<br />
                                        </p>
                                    </div>
                                    {/foreach}
                                {else}
                                    {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
                                    <div class="sidebar-row">
                                        <div class="control-group">
                                            <div class="controls">
                                                <input type="radio"
                                                    name="select_pickup_point[{$group_key}][{$shipping_id}]"
                                                    value="{$pickup_point.pickup_point_code}"
                                                    {if $select_id == $pickup_point.pickup_point_code || $old_office_id == $pickup_point.pickup_point_code}checked="checked"{/if}
                                                    id="office_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}"
                                                    data-ca-pickup-select-office="true"
                                                    data-ca-shipping-id="{$shipping_id}"
                                                    data-ca-group-key="{$group_key}"
                                                    data-ca-location-id="{$pickup_point.pickup_point_code}">
                                                {$pickup_point.pickup_point_code}{if $pickup_point.city_name}, {$pickup_point.city_name}{/if} {include file="common/tooltip.tpl" tooltip=$pickup_point.notes}
                                                <p class="muted">
                                                    {$pickup_point.address}<br />
                                                    {$pickup_point.work_time}<br />
                                                    {$pickup_point.phone}<br />
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
