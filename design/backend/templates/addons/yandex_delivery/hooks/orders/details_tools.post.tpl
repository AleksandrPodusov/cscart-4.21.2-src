{if $yandex_delivery_has_labels}
    <li>{btn
        type="list"
        text=__("yandex_delivery.print_labels")
        href="orders.yandex_delivery_labels?order_id=`$order_info.order_id`"
        method="POST"
    }</li>
{/if}
