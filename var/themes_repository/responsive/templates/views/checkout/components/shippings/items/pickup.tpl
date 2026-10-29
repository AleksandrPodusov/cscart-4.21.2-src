{$pickup_point_extra_data = $pickup_point.extra_data}

<label for="store_{$group_key}_{$shipping.shipping_id}_{$pickup_point.pickup_point_code}"
       class="ty-one-store js-pickup-search-block-{$group_key} {if ($old_pickup_point_code === $pickup_point.pickup_point_code) || ($store_count === 1)}ty-sdek-office__selected{/if}"
>
    <input type="radio"
           class="ty-one-store__radio ty-one-store__radio--{$group_key} cm-{$shipping_tag}-select-store"
           name="select_pickup_point[{$group_key}][{$shipping.shipping_id}]"
           value="{$pickup_point.pickup_point_code}"
            {if $old_pickup_point_code == $pickup_point.pickup_point_code || $store_count === 1}
                checked="checked"
            {/if}
           id="store_{$group_key}_{$shipping.shipping_id}_{$pickup_point.pickup_point_code}"
           data-ca-pickup-select-store="true"
           data-ca-shipping-id="{$shipping.shipping_id}"
           data-ca-group-key="{$group_key}"
           data-ca-location-id="{$pickup_point.pickup_point_code}"
    />

    <div class="ty-sdek-store__label ty-one-store__label">
        <p class="ty-one-store__name">
            <span class="ty-one-store__name-text">
                {if $pickup_point.pickup_point_name}
                    {$pickup_point.pickup_point_name}
                {else}
                    {$pickup_point.pickup_point_code}
                {/if}
            </span>
            {if !empty($shipping.free_shipping)}
                <span class="ty-one-store__name-rate">
                ({__("free")})
            </span>
            {elseif $pickup_point_extra_data.pickup_rate && $pickup_point_extra_data.pickup_rate > 0}
                <span class="ty-one-store__name-rate">
                ({include file="common/price.tpl" value=$pickup_point_extra_data.pickup_rate})
            </span>
            {/if}
        </p>

        <div class="ty-one-store__description">
            {if $pickup_point.address}
                <span class="ty-one-office__address">{$pickup_point.address}</span>
                <br />
            {/if}
            {if $pickup_point.work_time}
                <span class="ty-one-office__worktime">{$pickup_point.work_time}</span>
                <br />
            {/if}
            {if $pickup_point.phone}
                <span class="ty-one-office__worktime">{$pickup_point.phone}</span>
                <br />
            {/if}
            {if $pickup_point.metro}
                <span class="ty-one-office__worktime">{__('lite_checkout.nearest_station')}: {$pickup_point.metro}</span>
            {/if}
        </div>
    </div>
</label>
