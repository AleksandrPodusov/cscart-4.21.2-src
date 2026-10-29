{if $product_groups}
    {foreach from=$product_groups key=group_key item=group}
        {if $group.shippings && !$group.shipping_no_required}
            {foreach from=$group.shippings item=shipping}
                {if $cart.chosen_shipping.$group_key == $shipping.shipping_id && $shipping.module === "yandex_delivery" && $shipping.service_params.delivery_type === "self_pickup"}
                    {$shipping_id = $shipping.shipping_id}
                    {$selected_pickup_point_code = $select_pickup_point.$group_key.$shipping_id}

                    {if $pickup_points.$group_key.$shipping_id}
                        {$pickup_points_count = $pickup_points.$group_key.$shipping_id|count}

                        {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
                            <div class="sidebar-row">
                                <div class="control-group">
                                    <div class="controls">
                                        {if $pickup_points_count == 1}
                                            <input type="hidden"
                                                   name="select_pickup_point[{$group_key}][{$shipping_id}]"
                                                   value="{$pickup_point.pickup_point_code}"
                                                   id="pickup_point_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}"
                                                   checked="checked"
                                            />
                                            {$pickup_point.pickup_point_name}{if $pickup_point.city_name}, {$pickup_point.city_name}{/if}
                                            {if $pickup_point.notes}{include file="common/tooltip.tpl" tooltip=$pickup_point.notes}{/if}
                                        {else}
                                            <label for="pickup_point_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}" class="radio">
                                                <input type="radio"
                                                       name="select_pickup_point[{$group_key}][{$shipping_id}]"
                                                       value="{$pickup_point.pickup_point_code}"
                                                       {if $selected_pickup_point_code == $pickup_point.pickup_point_code}checked="checked"{/if}
                                                       id="pickup_point_{$group_key}_{$shipping_id}_{$pickup_point.pickup_point_code}"
                                                />
                                                {$pickup_point.pickup_point_name}{if $pickup_point.city_name}, {$pickup_point.city_name}{/if}
                                                {if $pickup_point.notes}{include file="common/tooltip.tpl" tooltip=$pickup_point.notes}{/if}
                                            </label>
                                        {/if}
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
            {/foreach}
        {/if}
    {/foreach}
{/if}
