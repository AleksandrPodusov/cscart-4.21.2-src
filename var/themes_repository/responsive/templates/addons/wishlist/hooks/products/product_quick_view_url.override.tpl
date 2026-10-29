{if $is_wishlist}
    {$show_quick_view_for_options_param = ($add_to_cart_stepper_type === "quick_view_for_options_link") ? "&show_quick_view_for_options=Y" : ""}
    {"products.quick_view?product_id=`$product.product_id``$show_quick_view_for_options_param`"}{if $product.combination}{"&combination=`$product.combination`"}{/if}{"&prev_url=`$current_url`"}
{/if}