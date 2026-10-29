{if $show_add_to_cart_stepper}{strip}
    {$is_add_to_cart_stepper_hidden = true}
    {$obj_id_int = $obj_id|intval}
    {$product_product_id_int = $product.product_id|intval}
    {$add_to_cart_meta = "`$add_to_cart_meta` ty-btn__add-to-cart--stepper cm-btn-add-to-cart-stepper"}
    {$quick_view_for_options_link_class = "`$quick_view_for_options_link_class` ty-btn__add-to-cart--stepper"}
    {$product_total_in_cart = 0}
    {$cart_product = []}
    {$show_quantity = true}
    {$show_in_cart_with_quantity = false}
    {$add_to_cart_stepper_type = $add_to_cart_stepper_type|default:""}
    {$add_to_cart_stepper_cart_aggregate = $add_to_cart_stepper_cart_aggregate|default:false}

    {hook name="buttons:add_to_cart_stepper_process"}

    {foreach $cart_products_for_add_to_cart_stepper as $key => $cart_product_inner}
        {$cart_product_inner_product_id_int = $cart_product_inner.product_id|intval}

        {$in_aggregate_family = false}
        {if $add_to_cart_stepper_cart_aggregate
            && isset($add_to_cart_stepper_cart_product_ids[$cart_product_inner_product_id_int])
        }
            {$in_aggregate_family = true}
        {/if}

        {if !$add_to_cart_stepper_cart_aggregate}
            {if $cart_product_inner_product_id_int === $product_product_id_int}
                {$product_total_in_cart = $product_total_in_cart + $cart_product_inner.amount}

                {* For quick_view_for_options_link.tpl *}
                {if $product.has_options && $add_to_cart_stepper_type === "quick_view_for_options_link" && !isset($fallback_cart_key)}
                    {$fallback_cart_key = $key}
                    {$fallback_cart_product = $cart_product_inner}
                {/if}
            {/if}
        {else}
            {if $in_aggregate_family}
                {$product_total_in_cart = $product_total_in_cart + $cart_product_inner.amount}

                {if $add_to_cart_stepper_type === "quick_view_for_options_link"}
                    {if $cart_product_inner_product_id_int === $obj_id_int}
                        {$fallback_cart_key = $key}
                        {$fallback_cart_product = $cart_product_inner}
                    {elseif !isset($fallback_cart_key)}
                        {$fallback_cart_key = $key}
                        {$fallback_cart_product = $cart_product_inner}
                    {/if}
                {/if}
            {/if}
        {/if}

        {if $cart_product_inner_product_id_int === $obj_id_int
            || ($add_to_cart_stepper_cart_aggregate && $in_aggregate_family)
        }
            {$options_match = false}
            {if $cart_product_inner.product_options}
                {if count($cart_product_inner.product_options) === count($product.selected_options)}
                    {$options_match = true}
                    {foreach $cart_product_inner.product_options as $cart_product_option_key => $cart_product_option_value}
                        {$cart_product_option_value_int = $cart_product_option_value|intval}
                        {if !isset($product.selected_options[$cart_product_option_key])
                            || (($product.selected_options[$cart_product_option_key])|intval) !== $cart_product_option_value_int
                        }
                            {$options_match = false}
                        {/if}
                    {/foreach}
                {/if}
            {else}
                {$options_match = empty($product.selected_options)}
            {/if}

            {if $options_match
                && ($cart_product_inner_product_id_int === $obj_id_int
                    || ($add_to_cart_stepper_cart_aggregate && !$cart_product.cart_id)
                )
            }
                {$is_add_to_cart_stepper_hidden = false}
                {$cart_product = $cart_product_inner}
                {$cart_product.cart_id = $key}
                {$add_to_cart_meta = "`$add_to_cart_meta` hidden"}
            {/if}
        {/if}
    {/foreach}

    {* For quick_view_for_options_link.tpl *}
    {if $add_to_cart_stepper_type === "quick_view_for_options_link"
        && ($product.has_options || $add_to_cart_stepper_cart_aggregate)
    }
        {$show_quantity = false}
        {if $product_total_in_cart > 0}
            {$is_add_to_cart_stepper_hidden = false}
            {$show_in_cart_with_quantity = true}
            {if !$cart_product.cart_id && isset($fallback_cart_key)}
                {$cart_product = $fallback_cart_product}
                {$cart_product.cart_id = $fallback_cart_key}
            {/if}
        {/if}
    {/if}

    {if $cart_product.cart_id}
        {$cart_product.qty_step = $product.qty_step}
        {$cart_product.exclude_from_calculate = $product.exclude_from_calculate}
        {$cart_product.min_qty = $product.min_qty}
    {/if}

    {/hook}

    {* Export *}
    {$is_add_to_cart_stepper_hidden = $is_add_to_cart_stepper_hidden scope=parent}
    {$cart_product = $cart_product scope=parent}
    {$add_to_cart_meta = $add_to_cart_meta scope=parent}
    {$quick_view_for_options_link_class = $quick_view_for_options_link_class scope=parent}
    {$product_total_in_cart = $product_total_in_cart scope=parent}
    {$show_quantity = $show_quantity scope=parent}
    {$show_in_cart_with_quantity = $show_in_cart_with_quantity scope=parent}
    {$add_to_cart_stepper_cart_aggregate = $add_to_cart_stepper_cart_aggregate scope=parent}
{/strip}{/if}