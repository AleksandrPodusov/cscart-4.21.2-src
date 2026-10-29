{foreach $order_info.shipping as $shipping_method}
    {if $shipping_method.module === 'sdek2'}
        {$sdek_shipping = true}
    {/if}
{/foreach}

{if $sdek_shipping}
    {foreach $order_info.shipping as $shipping_method}
        <li>{if $shipping_method.office_data}
                <p class="ty-strong">
                    {$shipping_method.office_data.code}
                </p>
                <p class="ty-muted">
                    {$shipping_method.office_data.location.city}, {$shipping_method.office_data.location.address}<br />
                    {$shipping_method.office_data.work_time}<br /><br />
                    {if $shipping_method.office_data.phones}
                        {foreach $shipping_method.office_data.phones as $phone}
                            {$phone.number}<br />
                        {/foreach}
                        <br />
                    {/if}
                    {$shipping_method.office_data.note}<br />
                </p>
            {/if}
        </li>
    {/foreach}
{/if}