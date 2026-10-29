{$show_input_password = $show_input_password|default:true}{if $show_input_password}{strip}
{*
    Import
    ---
    id                          string      required*   Input: ID
    name                        string      required*   Input: name
    value                       string                  Input: value
    class                       string                  Input: class
    new_password                bool                    Input: enable autocomplete="new-password"
    input_attrs                 array<string, string>   Input: Another attributes
    input_group_class           string                  Input group (level 3): class
    toggle_button_class         string                  Toggle button (level 4): class
    eye_close_icon_class        string                  Eye close icon: class
    eye_open_icon_class         string                  Eye open icon: class
    show_input_password         bool                    Show or hide input password

    *A form field element should have an id or name attribute


    Example #1: default (without label)
    ---
    {include_ext file="components/input_password.tpl"
        name="example_name"
        value="example_value"
    }
    Example #2: with params
    ---
    {include_ext file="components/input_password.tpl"
        id="example_id"
        name="example_name"
        value="example_value"
        class="example-input-class"
        input_attrs=[
            autocomplete => "new-password",
            tabindex => "2",
            maxlength => "32",
            "data-ca-param-1" => "value_1",
            "data-ca-param-2" => "value_2"
        ]
        input_group_class="example-input-group-class"
        toggle_button_class="example-toggle-button-class"
        eye_close_icon_class="example-eye-close-icon-class"
        eye_open_icon_class="example-eye-open-icon-class"
    }

    Script: js/tygh/components/input_password.js
*}

{* Input attributes *}
{$internal_input_attrs = $input_attrs}
{$internal_input_attrs["type"] = "password"}
{if $id}
    {$internal_input_attrs["id"] = $id}
{/if}
{if $name}
    {$internal_input_attrs["name"] = $name}
{/if}
{if $value}
    {$internal_input_attrs["value"] = $value}
{/if}
{if $class}
    {$internal_input_attrs["class"] = "cs-input-password__input `$class`"}
{elseif $internal_input_attrs["class"]}
    {$internal_input_attrs["class"] = "cs-input-password__input `$internal_input_attrs["class"]`"}
{else}
    {$internal_input_attrs["class"] = "cs-input-password__input"}
{/if}
{if $new_password}
    {$internal_input_attrs["autocomplete"] = "new-password"}
{/if}
{$internal_input_attrs["data-ca-input-password"] = "input"}

<div class="input-group cs-input-password {$input_group_class}" data-ca-input-password="inputGroup">
    <input {$internal_input_attrs|render_tag_attrs nofilter} />
    <button {""}
        type="button" {""}
        class="cs-input-password__toggle-button {$toggle_button_class}" {""}
        data-ca-input-password="toggleButton" {""}
    >{include_ext
        file="common/icon.tpl"
        class="ty-icon-eye-close cs-input-password__eye-close-icon `$eye_close_icon_class`"
        data=[
            "data-ca-input-password" => "eyeCloseIcon"
        ]
    }{include_ext
        file="common/icon.tpl"
        class="ty-icon-eye-open cs-input-password__eye-open-icon hidden `$eye_open_icon_class`"
        data=[
            "data-ca-input-password" => "eyeOpenIcon"
        ]
    }</button>
</div>
{/strip}{/if}