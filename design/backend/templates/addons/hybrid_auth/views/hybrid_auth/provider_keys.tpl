<div id="content_keys_{$id}">
    
    {if $providers_schema[$provider].instruction}
    <div class="control-group">
        <div class="controls">
            <div class="alert alert-block hybrid-auth-instruction">
                {__($providers_schema[$provider].instruction) nofilter}
            </div>
        </div>
    </div>
    {/if}

    {foreach from=$providers_schema[$provider].keys item="key" key="key_id"}
        {if $key.type === "password"}
            {include_ext file="components/input_password.tpl"
                id="section_`$key_id`_`$id`"
                name="provider_data[`$key.db_field`]"
                value=$provider_data[$key.db_field]
                required=$key.required
                label_text=__($key.label)
            }
        {else}
            <div class="control-group">
                <label for="section_{$key_id}_{$id}" class="control-label{if $key.required} cm-required{/if}">{__($key.label)}:</label>
                <div class="controls">
                    <input type="text" name="provider_data[{$key.db_field}]" size="30" value="{$provider_data[$key.db_field]}" id="section_{$key_id}_{$id}">
                </div>
            </div>
        {/if}
    {/foreach}
<!--content_keys_{$id}--></div>