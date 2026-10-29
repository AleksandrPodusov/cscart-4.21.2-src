{if !$nothing_extra}
    {include file="common/subheader.tpl" title=__("user_account_info")}
{/if}

{hook name="profiles:account_info"}
    <div class="ty-control-group">
        <label for="email" class="ty-control-group__title cm-required cm-email cm-trim">{__("email")}</label>
        <input type="text" id="email" name="user_data[email]" size="32" maxlength="128" value="{$user_data.email}" class="ty-input-text cm-focus" />
    </div>

    <div class="ty-control-group">
        <label for="password1" class="ty-control-group__title cm-password {if $runtime.mode !== "update"}cm-required{/if}">{if $runtime.mode === "update"}{__("new_password")}{else}{__("password")}{/if}</label>
        {include_ext file="components/input_password.tpl"
            id="password1"
            name="user_data[password1]"
            class="ty-input-text cm-autocomplete-off"
            input_attrs=[
                maxlength => "32"
            ]
            input_group_class="ty-width-full"
            toggle_button_class="ty-btn"
        }
    </div>

    <div class="ty-control-group">
        <label for="password2" class="ty-control-group__title cm-password {if $runtime.mode !== "update"}cm-required{/if}">{if $runtime.mode === "update"}{__("confirm_new_password")}{else}{__("confirm_password")}{/if}</label>
        {include_ext file="components/input_password.tpl"
            id="password2"
            name="user_data[password2]"
            class="ty-input-text cm-autocomplete-off"
            input_attrs=[
                maxlength => "32"
            ]
            input_group_class="ty-width-full"
            toggle_button_class="ty-btn"
        }
    </div>
{/hook}