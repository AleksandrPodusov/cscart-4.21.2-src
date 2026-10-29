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
    label_control_group_class   string                  Label control group (level 1): class
    label_text                  string                  Label: text
    label_class                 string                  Label: class
    required                    bool                    Label: required (for input)
    label_controls_class        string                  Label controls (level 2): class
    input_group_class           string                  Input group (level 3): class
    toggle_button_class         string                  Toggle button (level 4): class
    eye_close_icon_class        string                  Eye close icon: class
    eye_open_icon_class         string                  Eye open icon: class
    show_input_password         bool                    Show or hide input password
    tooltip                     string                  Tooltip: text
    tooltip_class               string                  Tooltip: class

    *A form field element should have an id or name attribute


    Example #1: default (without label)
    ---
    {include_ext file="components/input_password.tpl"
        name="example_name"
        value="example_value"
    }
    Example #2: with label
    ---
    {include_ext file="components/input_password.tpl"
        name="example_name"
        value="example_value"
        label_text=__("example_label_text")
    }
    Example #3: with params
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
        label_control_group_class="example-label-control-group-class"
        label_text=__("example_label_text")
        label_class="example-label-class"
        required=true
        label_controls_class="example-label-controls-class"
        input_group_class="example-input-group-class"
        toggle_button_class="example-toggle-button-class"
        eye_close_icon_class="example-eye-close-icon-class"
        eye_open_icon_class="example-eye-open-icon-class"
        tooltip=__("example_tooltip")
        tooltip_class="example-tooltip-class"
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

{if $label_text}
<div class="control-group cs-input-password__control-group {$label_control_group_class}">
    <label for="{$id}" class="control-label cs-input-password__control-label {if $required}cm-required{/if} {$label_class}">{$label_text}:</label>
    <div class="controls cs-input-password__controls {$label_controls_class}">
{/if}
        <div class="input-group input-append cs-input-password {$input_group_class}" data-ca-input-password="inputGroup">
            <input {$internal_input_attrs|render_tag_attrs nofilter} />
            <button {""}
                type="button" {""}
                class="btn cs-input-password__toggle-button {$toggle_button_class}" {""}
                data-ca-input-password="toggleButton" {""}
            >{include_ext
                file="common/icon.tpl"
                source="eye_close"
                class="cs-input-password__eye-close-icon `$eye_close_icon_class`"
                data=[
                    "data-ca-input-password" => "eyeCloseIcon"
                ]
            }{include_ext
                file="common/icon.tpl"
                source="eye_open"
                class="cs-input-password__eye-open-icon hidden `$eye_open_icon_class`"
                data=[
                    "data-ca-input-password" => "eyeOpenIcon"
                ]
            }</button>
        </div>
        {if $tooltip}
            <div class="muted description {$tooltip_class}">{$tooltip nofilter}</div>
        {/if}
{if $label_text}
    </div>
</div>
{/if}
{/strip}{/if}