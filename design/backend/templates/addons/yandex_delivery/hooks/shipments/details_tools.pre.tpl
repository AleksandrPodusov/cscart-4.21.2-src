{if $yandex_delivery_is_shipment}
    {if $yandex_delivery_request.request_id}
        <li>{btn
            type="list"
            text=__("yandex_delivery.print_label")
            href="shipments.yandex_delivery_label?shipment_id=`$shipment.shipment_id`"
            method="POST"
        }</li>
        <li>{btn
            type="list"
            text=__("yandex_delivery.update_status")
            href="shipments.yandex_delivery_update_status?shipment_id=`$shipment.shipment_id`"
            method="POST"
        }</li>
    {/if}
    <li class="divider"></li>
{/if}
