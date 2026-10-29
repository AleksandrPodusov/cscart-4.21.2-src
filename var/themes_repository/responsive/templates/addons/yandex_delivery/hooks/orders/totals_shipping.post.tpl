{foreach $order_info.shipping as $shipping_method}
    {if $shipping_method.module === "yandex_delivery" && ($shipping_method.pickup_point_data || $shipping_method.pickup_point_id)}
        {$pickup_point = $shipping_method.pickup_point_data|default:[]}
        {$pickup_point_id = $shipping_method.pickup_point_id|default:$pickup_point.platform_station_id|default:$pickup_point.code|default:""}
        {$pickup_point_name = $pickup_point.name|default:$pickup_point.location.address|default:$pickup_point_id}
        {$pickup_point_address = $pickup_point.location.address_full|default:$pickup_point.location.address|default:""}
        {$pickup_point_phone = $pickup_point.contact.phone|default:""}
        {$pickup_point_work_time = $pickup_point.work_time|default:""}
        {$pickup_point_description = $pickup_point.address_comment|default:$pickup_point.instruction|default:""}

        <p class="ty-strong">
            {__("yandex_delivery.pickup_point")}
        </p>
        <p class="ty-muted">
            {if $pickup_point_name}
                {$pickup_point_name}<br />
            {/if}
            {if $pickup_point_address && $pickup_point_address !== $pickup_point_name}
                {$pickup_point_address}<br />
            {/if}
            {if $pickup_point_phone}
                {__("phone")}: {$pickup_point_phone}<br />
            {/if}
            {if $pickup_point_work_time}
                {__("open_hours")}: {$pickup_point_work_time}<br />
            {/if}
            {if $pickup_point_description}
                {$pickup_point_description}<br />
            {/if}
        </p>
    {/if}
{/foreach}
