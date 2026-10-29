<div class="control-group">
    <label class="control-label" for="username">{__("username")}:</label>
    <div class="controls">
    	<input type="text" name="payment_data[processor_params][username]" id="username" value="{$processor_params.username}"   size="40">
    </div>
</div>

{include_ext file="components/input_password.tpl"
    id="password"
    name="payment_data[processor_params][password]"
    value=$processor_params.password
    label_text=__("password")
}

<div class="control-group">
    <label class="control-label" for="test">{__("test_live_mode")}:</label>
    <div class="controls">
    	<select name="payment_data[processor_params][test]" id="test">
    	    <option value="Y" {if $processor_params.test == "Y"}selected="selected"{/if}>{__("test")}</option>
    	    <option value="N" {if $processor_params.test == "N"}selected="selected"{/if}>{__("live")}</option>
    	</select>
    </div>
</div>