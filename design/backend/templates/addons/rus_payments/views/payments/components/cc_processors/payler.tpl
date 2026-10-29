{$r_url=fn_url("payment_notification.result?payment=payler&order_id={$ldelim}order_id{$rdelim}", 'C', 'http')}

<p>{__("text_payler_notice", ["[result_url]" => $r_url|unescape:'url']) nofilter}</p>

{include_ext file="components/input_password.tpl"
    id="key"
    name="payment_data[processor_params][key]"
    value=$processor_params.key
    label_text=__("key")
}
    
<div class="control-group">
    <label class="control-label" for="mode">{__("test_live_mode")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][mode]" id="mode">
            <option value="test" {if $processor_params.mode == "test"}selected="selected"{/if}>{__("test")}</option>
            <option value="live" {if $processor_params.mode == "live"}selected="selected"{/if}>{__("live")}</option>
        </select>
    </div>
</div>