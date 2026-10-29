{if $rus_taxes_show_marketplace_fee_fiscal_settings}
    <div class="control-group">
        <div class="controls">
            <div class="well well-small help-block">
                {__("rus_taxes.marketplace_fee_online_cash_intro") nofilter}
            </div>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="elm_marketplace_fee_send_receipt">{__("rus_taxes.give_vendor_to_admin_receipts")}:</label>
        <div class="controls">
            <input type="hidden" name="rus_taxes_marketplace_fee[send_receipt]" value="{"YesNo::NO"|enum}" />
            <label class="checkbox">
                <input type="checkbox"
                    id="elm_marketplace_fee_send_receipt"
                    name="rus_taxes_marketplace_fee[send_receipt]"
                    value="{"YesNo::YES"|enum}"
                    {if $rus_taxes_marketplace_fee.send_receipt == "YesNo::YES"|enum}checked="checked"{/if}
                />
            </label>
            <div class="muted description">{__("ttc_rus_taxes.give_vendor_to_admin_receipts") nofilter}</div>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="elm_rus_taxes_marketplace_fee_fiscal_1212">{__("rus_taxes.marketplace_fee_payment_object")}:</label>
        <div class="controls">
            <select id="elm_rus_taxes_marketplace_fee_fiscal_1212" name="product_data[fiscal_data_1212]">
                <optgroup label="{__("recommended")}">
                    {foreach $marketplace_fee_fiscal_1212_groups.recommended as $fiscal_data_1212_type}
                        <option value="{$fiscal_data_1212_type}"{if $rus_taxes_marketplace_fee.fiscal_data_1212|intval === $fiscal_data_1212_type} selected="selected"{/if}>
                            {__($marketplace_fee_fiscal_data_1212_objects[$fiscal_data_1212_type])}
                        </option>
                    {/foreach}
                </optgroup>
                <optgroup label="{__("rus_taxes.marketplace_fee_fiscal_group.other")}">
                    {foreach $marketplace_fee_fiscal_1212_groups.other as $fiscal_data_1212_type}
                        <option value="{$fiscal_data_1212_type}"{if $rus_taxes_marketplace_fee.fiscal_data_1212|intval === $fiscal_data_1212_type} selected="selected"{/if}>
                            {__($marketplace_fee_fiscal_data_1212_objects[$fiscal_data_1212_type])}
                        </option>
                    {/foreach}
                </optgroup>
            </select>
        </div>
    </div>

    <div class="control-group">
        <label class="control-label" for="elm_rus_taxes_marketplace_fee_fiscal_1214">{__("rus_taxes.marketplace_fee_payment_type")}:</label>
        <div class="controls">
            <select id="elm_rus_taxes_marketplace_fee_fiscal_1214" name="rus_taxes_marketplace_fee[fiscal_data_1214]">
                <optgroup label="{__("recommended")}">
                    {foreach $marketplace_fee_fiscal_1214_groups.recommended as $fiscal_data_1214_type}
                        <option value="{$fiscal_data_1214_type}"{if $rus_taxes_marketplace_fee.fiscal_data_1214|intval === $fiscal_data_1214_type} selected="selected"{/if}>
                            {__($marketplace_fee_fiscal_data_1214_objects[$fiscal_data_1214_type])}
                        </option>
                    {/foreach}
                </optgroup>
                <optgroup label="{__("rus_taxes.marketplace_fee_fiscal_group.other")}">
                    {foreach $marketplace_fee_fiscal_1214_groups.other as $fiscal_data_1214_type}
                        <option value="{$fiscal_data_1214_type}"{if $rus_taxes_marketplace_fee.fiscal_data_1214|intval === $fiscal_data_1214_type} selected="selected"{/if}>
                            {__($marketplace_fee_fiscal_data_1214_objects[$fiscal_data_1214_type])}
                        </option>
                    {/foreach}
                </optgroup>
            </select>
        </div>
    </div>
{/if}
