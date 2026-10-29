{foreach $order_info.shipping as $shipping}
    {if $shipping.module === "yandex_delivery" && ($shipping.pickup_point_data || $shipping.pickup_point_id)}
        {$pickup_point = $shipping.pickup_point_data|default:[]}
        {$pickup_point_id = $shipping.pickup_point_id|default:$pickup_point.platform_station_id|default:$pickup_point.code|default:""}
        {$pickup_point_name = $pickup_point.name|default:$pickup_point.location.address|default:$pickup_point_id}
        {$pickup_point_address = $pickup_point.location.address_full|default:$pickup_point.location.address|default:""}
        {$pickup_point_phone = $pickup_point.contact.phone|default:""}
        {$pickup_point_work_time = $pickup_point.work_time|default:""}
        {$pickup_point_description = $pickup_point.address_comment|default:$pickup_point.instruction|default:""}

        <div class="well orders-right-pane form-horizontal">
            <div class="control-group shift-top">
                <div class="control-label">
                    {include file="common/subheader.tpl" title=__("yandex_delivery.pickup_point")}
                </div>
            </div>

            {if $pickup_point_name}
                <p class="strong">{$pickup_point_name}</p>
            {/if}

            <p class="muted">
                {if $pickup_point_address && $pickup_point_address !== $pickup_point_name}
                    {$pickup_point_address}<br />
                {/if}
                {if $pickup_point_phone}
                    {__("phone")}: <bdi>{$pickup_point_phone}</bdi><br />
                {/if}
                {if $pickup_point_work_time}
                    {__("open_hours")}: {$pickup_point_work_time}<br />
                {/if}
                {if $pickup_point_description}
                    {$pickup_point_description}<br />
                {/if}
            </p>
        </div>
    {/if}
{/foreach}
