{strip}
{if !$hide_wishlist_button && $show_wishlist_button_on_image_panel}
    {$image_panel_items.top_right.add_to_wishlist = [
        type => "a",
        insert_before_id => "add_to_compare",
        id => "button_wishlist_`$obj_prefix``$product.product_id`",
        class => "ty-btn ty-btn__tertiary ty-btn-icon ty-add-to-wish cm-submit",
        title => __("add_to_wishlist"),
        icon => "ty-icon-heart",
        element_attrs => [
            "data-ca-dispatch" => "dispatch[wishlist.add..`$product.product_id`]"
        ]
    ]}
{/if}

{$image_panel_items = $image_panel_items scope=parent}
{/strip}
