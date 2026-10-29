{$_max_desktop_items = $max_desktop_items|default:5}
{if $settings.geo_maps.general.provider === "yandex"}
    {$show_move_map_mobile_hint = true}
{/if}

{$map_container_id = "map_container_`$shipping.shipping_id`_`$shipping.group_key`"}

<div class="litecheckout__item ty-checkout-select-store__map-full-div pickup pickup--map-list">

    {* Map *}
    <div class="ty-checkout-select-store__map pickup__map-wrapper">
        {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
            {$pickup_point_extra_data = $pickup_point.extra_data}

            {* The map balloon shows this as its own header, and a cluster balloon lists its points by it. *}
            {if $pickup_point.pickup_point_name}
                {$marker_header = $pickup_point.pickup_point_name}
            {else}
                {$marker_header = $pickup_point.pickup_point_code}
            {/if}

            {capture name="marker_content"}
                <div class="litecheckout-ya-baloon">
                    {if $pickup_point_extra_data.pickup_rate && $pickup_point_extra_data.pickup_rate > 0}
                        <p class="litecheckout-ya-baloon__store-rate">
                            {include file="common/price.tpl" value=$pickup_point_extra_data.pickup_rate}
                        </p>
                    {/if}

                    {if $pickup_point.address}<p class="litecheckout-ya-baloon__store-address">{$pickup_point.address}</p>{/if}

                    <p class="litecheckout-ya-baloon__select-row">
                        <a data-ca-shipping-id="{$shipping.shipping_id}"
                           data-ca-group-key="{$group_key}"
                           data-ca-location-id="{$pickup_point.pickup_point_code}"
                           data-ca-target-map-id="{$map_container_id}"
                           class="cm-{$shipping_tag}-select-location ty-btn ty-btn__primary text-button ty-width-full"
                        >{__("select")}</a>
                    </p>

                    {if $pickup_point.phone}<p class="litecheckout-ya-baloon__store-phone"><a href="tel:{$pickup_point.phone}">{$pickup_point.phone}</a></p>{/if}
                    {if $pickup_point.work_time}<p class="litecheckout-ya-baloon__store-time">{$pickup_point.work_time}</p>{/if}
                    {if $pickup_point.notes}<div class="litecheckout-ya-baloon__store-description">{$pickup_point.notes nofilter}</div>{/if}
                </div>
            {/capture}

            {$is_store_selected = $old_pickup_point_code === $pickup_point.pickup_point_code || $store_count === 1}

            {if $is_store_selected}
                {$selected_store_lat = $pickup_point.latitude}
                {$selected_store_lng = $pickup_point.longitude}
            {/if}

            <div class="cm-{$shipping_tag}-map-marker-{$shipping.shipping_id}-{$group_key} hidden"
                 data-ca-geo-map-marker-lat="{$pickup_point.latitude}"
                 data-ca-geo-map-marker-lng="{$pickup_point.longitude}"
                 data-ca-geo-map-marker-header="{$marker_header}"
                    {if $is_store_selected}
                        data-ca-geo-map-marker-selected="true"
                    {/if}
            >{$smarty.capture.marker_content nofilter}</div>

            {if $pickup_point.latitude && $pickup_point.longitude}
                {$initial_lat = $selected_store_lat|default:$pickup_point.latitude}
                {$initial_lng = $selected_store_lng|default:$pickup_point.longitude}
            {/if}

        {/foreach}
        <div class="pickup__map-container cm-geo-map-container" id="{$map_container_id}"
             data-ca-geo-map-initial-lat="{$initial_lat|default:$smarty.const.STORE_LOCATOR_DEFAULT_LATITUDE|doubleval}"
             data-ca-geo-map-initial-lng="{$initial_lng|default:$smarty.const.STORE_LOCATOR_DEFAULT_LONGITUDE|doubleval}"
             data-ca-geo-map-zoom="16"
             data-ca-geo-map-controls-enable-zoom="true"
             data-ca-geo-map-controls-enable-fullscreen="true"
             data-ca-geo-map-controls-enable-layers="true"
             data-ca-geo-map-controls-enable-ruler="true"
             data-ca-geo-map-behaviors-enable-drag="true"
             data-ca-geo-map-behaviors-enable-drag-on-mobile="false"
             data-ca-geo-map-behaviors-enable-smart-drag="true"
             data-ca-geo-map-behaviors-enable-dbl-click-zoom="true"
             data-ca-geo-map-behaviors-enable-multi-touch="true"
             data-ca-geo-map-language="{$smarty.const.CART_LANGUAGE}"
             data-ca-geo-map-marker-selector=".cm-{$shipping_tag}-map-marker-{$shipping.shipping_id}-{$group_key}"
        ></div>
        {if $show_move_map_mobile_hint}
            <div class="pickup__map-container--mobile-hint">{__("lite_checkout.use_two_fingers_for_move_map")}</div>
        {/if}
    </div>

    {* For mobiles; List wrapper with selected pickup item *}
    {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
        {if $old_pickup_point_code === $pickup_point.pickup_point_code}
            <div class="ty-checkout-select-store pickup__offices-wrapper visible-phone pickup__offices-wrapper--near-map">
                {* List *}
                <div class="litecheckout__fields-row litecheckout__fields-row--wrapped pickup__offices pickup__offices--list pickup__offices--list-no-height">
                    {include file="views/checkout/components/shippings/items/pickup.tpl" pickup_point=$pickup_point}
                </div>
                {* End of List *}
            </div>
        {/if}
    {/foreach}
    {* For mobiles; List wrapper with selected pickup item *}

    {* For mobiles; button for popup with pickup points *}
    <button class="ty-btn ty-btn__secondary cm-open-pickups pickup__open-pickupups-btn visible-phone"
            data-ca-title="{__('lite_checkout.choose_from_list')}"
            data-ca-target="[data-ca-shipping='pickup_offices_wrapper_open_{$group_key}_{$shipping.shipping_id}']"
            type="button"
    >{__('lite_checkout.choose_from_list')}</button>
    <span class="visible-phone cm-open-pickups-msg"></span>
    {* For mobiles; button for popup with pickup points *}

    {* List wrapper *}
    <div data-ca-shipping="pickup_offices_wrapper_open_{$group_key}_{$shipping.shipping_id}" class="ty-checkout-select-store pickup__offices-wrapper hidden-phone">

        {* Search *}
        {if $shipping.data.pickup_points_count >= $_max_desktop_items}
            <div class="pickup__search">
                <div class="pickup__search-field litecheckout__field">
                    <input type="text" id="pickup-search" class="litecheckout__input js-pickup-search-input" data-ca-pickup-group-key="{$group_key}" placeholder=" "
                           value />
                    <label class="litecheckout__label" for="pickup-search">{__("storefront_search_label")}</label>
                </div>
            </div>
        {/if}
        {* End of Search *}

        {* List *}
        <label for="pickup_office_list"
               class="cm-required cm-multiple-radios hidden"
               data-ca-validator-error-message="{__("pickup_point_not_selected")}"></label>
        <div class="litecheckout__fields-row litecheckout__fields-row--wrapped pickup__offices pickup__offices--list
            {if $shipping.data.pickup_points_count < $_max_desktop_items}
                pickup__offices--list--no-sorting
            {/if}"
             id="pickup_office_list"
             data-ca-error-message-target-node-change-on-screen="xs,xs-large,sm"
             data-ca-error-message-target-node-after-mode="true"
             data-ca-error-message-target-node-on-screen=".cm-open-pickups-msg"
             data-ca-error-message-target-node=".pickup__offices--list"
        >
            {foreach $pickup_points.$group_key.$shipping_id as $pickup_point}
                {include file="views/checkout/components/shippings/items/pickup.tpl" pickup_point=$pickup_point}
            {/foreach}
        </div>
        {* End of List *}

    </div>
    {* End of List wrapper *}

</div>