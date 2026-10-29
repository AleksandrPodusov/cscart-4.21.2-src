{if $yandex_delivery_is_shipment}
    {include file="common/subheader.tpl" title=__("yandex_delivery.request")}

    <div class="control-group">
        <label class="control-label">{__("yandex_delivery.request_id")}</label>
        <div class="controls">
            {if $yandex_delivery_request.request_id}
                {$yandex_delivery_request.request_id}
            {else}
                <span class="muted">{__("yandex_delivery.not_sent")}</span>
            {/if}
        </div>
    </div>

    {if $yandex_delivery_request.status}
        <div class="control-group">
            <label class="control-label">{__("yandex_delivery.status")}</label>
            <div class="controls">
                {$yandex_delivery_request.status}
                {if $yandex_delivery_request.status_description}
                    ({$yandex_delivery_request.status_description})
                {/if}
            </div>
        </div>
    {/if}

    {if $yandex_delivery_request.last_error}
        <div class="control-group">
            <label class="control-label">{__("error")}</label>
            <div class="controls text-error">{$yandex_delivery_request.last_error}</div>
        </div>
    {/if}
{/if}
