{if $shipment.carrier === "yandex_delivery" || $shipping.module === "yandex_delivery"}
    {$yandex_delivery_request = $yandex_delivery_requests[$shipment.shipment_id]|default:[]}

    <div class="control-group">
        <div class="control-label">{__("yandex_delivery.request")}</div>
        <div class="controls">
            {if $yandex_delivery_request.request_id}
                <p>
                    <strong>{__("yandex_delivery.request_id")}:</strong>
                    {$yandex_delivery_request.request_id}
                </p>
                {if $yandex_delivery_request.status}
                    <p>
                        <strong>{__("yandex_delivery.status")}:</strong>
                        {$yandex_delivery_request.status}
                        {if $yandex_delivery_request.status_description}
                            ({$yandex_delivery_request.status_description})
                        {/if}
                    </p>
                {/if}
                {if $yandex_delivery_request.last_error}
                    <p class="text-error">{$yandex_delivery_request.last_error}</p>
                {/if}
                <p>
                    {btn
                        type="text"
                        text=__("yandex_delivery.update_status")
                        href="orders.yandex_delivery_update_status?order_id=`$order_info.order_id`&shipment_id=`$shipment.shipment_id`"
                        method="POST"
                    }
                </p>
            {else}
                <p class="muted">{__("yandex_delivery.not_sent")}</p>
                {if $yandex_delivery_request.last_error}
                    <p class="text-error">{$yandex_delivery_request.last_error}</p>
                {/if}
                {btn
                    type="text"
                    text=__("yandex_delivery.create_request")
                    href="orders.yandex_delivery_create_request?order_id=`$order_info.order_id`&shipment_id=`$shipment.shipment_id`"
                    method="POST"
                    class="btn btn-primary"
                }
            {/if}
        </div>
    </div>
{/if}
