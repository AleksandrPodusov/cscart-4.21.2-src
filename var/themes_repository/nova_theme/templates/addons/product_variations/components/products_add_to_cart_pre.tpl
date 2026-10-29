{$add_to_cart_type = $add_to_cart_type|default:"text"}
{if $add_to_cart_type === "icon"}
    {$select_variation_but_text = " "}
    {$select_variation_but_icon = "ty-icon-cart ty-icon--no-margin"}
{else}
    {$select_variation_but_text = $select_variation_but_text|default:__("add_to_cart")}
    {$select_variation_but_icon = $add_to_cart_icon|default:" "}
{/if}

{if $show_quick_view_for_options}
    {include file="views/products/components/quick_view_for_options_link.tpl"
        quick_nav_ids=false
        quick_view_icon=$select_variation_but_icon
        quick_view_text=$select_variation_but_text
        quick_view_for_options_link_class="`$quick_view_for_options_link_class` cm-dialog-destroy-on-close"
        add_to_cart_stepper_type="quick_view_for_options_link"
    }

    {* Export *}
    {$show_select_variation = false scope=parent}
{/if}