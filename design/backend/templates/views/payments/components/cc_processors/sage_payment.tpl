<div class="control-group">
    <label class="control-label" for="merchant_id">{__("merchant_id")}:</label>
    <div class="controls">
    	<input type="text" name="payment_data[processor_params][merchant_id]" id="merchant_id" value="{$processor_params.merchant_id}"   size="60">
    </div>
</div>

{include_ext file="components/input_password.tpl"
    id="merchant_key"
    name="payment_data[processor_params][merchant_key]"
    value=$processor_params.merchant_key
    label_text=__("merchant_key")
}
