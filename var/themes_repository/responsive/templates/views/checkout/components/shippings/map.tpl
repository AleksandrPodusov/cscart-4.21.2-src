<div class="clearfix ty-checkout-select-store__map-full-div" style="width: 100%">
    {if $store_count > 1}
        <h3>{__("available")}: {$store_count}
        <div data-ca-group-key="{$group_key}"
             class="ty-checkout-select-store__item-view"
        >
            {include file="buttons/button.tpl"
                but_role="text"
                but_meta="cm-map-view-locations ty-btn__tertiary"
                but_text=__("lite_checkout.shippings.show_all_on_map")
            }
        </div>
        </h3>
    {/if}
    <div class="ty-checkout-select-store__map-full" id="{$map_container}"></div>

    {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
        {$pickup_point_extra_data = $pickup_point.extra_data}

        {if $old_pickup_point_code == $pickup_point.pickup_point_code || $store_count === 1}
            <div class="ty-one-store__select-store">
                <p class="ty-one-store__name">
                    {$pickup_point.pickup_point_name}
                    {if $pickup_point_extra_data.pickup_rate}({include file="common/price.tpl" value=$pickup_point_extra_data.pickup_rate}){/if}
                </p>
                <div class="ty-one-store__description">
                    {strip}
                    {$pickup_point.city}
                    {if $pickup_point.address}, {$pickup_point.address}{/if}<br/>
                    {if $pickup_point.phone}{__("phone")}: {$pickup_point.phone}<br/>{/if}
                    {if $pickup_point.work_time}{__("store_locator.work_time")}: {$pickup_point.work_time}<br/>{/if}
                    {if $pickup_point.notes}
                        <a id="sw_store_description_{$pickup_point.pickup_point_code}" {""}
                           class="cm-combination ty-cart-content__detailed-link detailed-link"
                        >{__("description")}</a>
                        <div id="store_description_{$pickup_point.pickup_point_code}" {""}
                             class="hidden"
                        >{$pickup_point.notes nofilter}</div>
                        <br/>
                    {/if}
                    {/strip}
                </div>
            </div>
        {/if}
        <input type="radio"
               class="ty-one-store__radio-{$group_key}  hidden"
               name="select_pickup_point[{$group_key}][{$shipping.shipping_id}]"
               value="{$pickup_point.pickup_point_code}"
               {if $old_pickup_point_code == $pickup_point.pickup_point_code || $store_count === 1}checked="checked"{/if}
               id="store_{$group_key}_{$shipping.shipping_id}_{$pickup_point.pickup_point_code}"
               data-ca-pickup-select-store="true"
               data-ca-shipping-id="{$shipping.shipping_id}"
               data-ca-group-key="{$group_key}"
               data-ca-location-id="{$pickup_point.pickup_point_code}"
        />
    {/foreach}
</div>
