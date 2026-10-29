{if $smarty.request.redirect_url}
    {$current_url = $smarty.request.redirect_url|urlencode}
{else}
    {$current_url = $config.current_url|urlencode}
{/if}
{capture name="quick_view_url"}
{** Sets quick view for options link *}
{hook name="products:product_quick_view_url"}
{"products.quick_view?product_id=`$product.product_id`&show_quick_view_for_options=Y&prev_url=`$current_url`"}
{/hook}
{/capture}
{$quick_view_url = $smarty.capture.quick_view_url|trim}
{if $block.type && $block.type != 'main'}
    {$quick_view_url = $quick_view_url|fn_link_attach:"n_plain=Y"}
{/if}
{if $quick_nav_ids}
    {$quick_nav_ids = ","|implode:$quick_nav_ids}
    {$quick_view_url = $quick_view_url|fn_link_attach:"n_items=`$quick_nav_ids`"}
{/if}

{hook name="products:product_quick_view_microstore"}{/hook}

{$show_add_to_cart_stepper = $show_add_to_cart_stepper|default:$theme_manifest_features.add_to_cart_stepper|default:false}
{if ($settings.Checkout.allow_anonymous_shopping === "allow_shopping" || $auth.user_id) && $show_add_to_cart_stepper}
    {* Get:
        $is_add_to_cart_stepper_hidden
        $cart_product
        $add_to_cart_meta
        $quick_view_for_options_link_class
        $product_total_in_cart
        $show_quantity
        $show_in_cart_with_quantity
    *}
    {include file="buttons/add_to_cart_stepper_process.tpl"
        product=$product
        cart_products_for_add_to_cart_stepper=$smarty.session.cart.products
        obj_id=$obj_id
        show_add_to_cart_stepper=$show_add_to_cart_stepper
        add_to_cart_meta=$add_to_cart_meta
        add_to_cart_stepper_type=$add_to_cart_stepper_type
    }
    {include_ext file="buttons/add_to_cart_stepper.tpl"
        cart_product=$cart_product
        obj_id=$obj_id
        obj_prefix=$obj_prefix
        reload_obj_id=$product_id
        reload_obj_prefix=((isset($product_id)) ? "" : $obj_prefix)
        redirect_url=$redirect_url
        show_add_to_cart_stepper=$show_add_to_cart_stepper
        is_add_to_cart_stepper_hidden=$is_add_to_cart_stepper_hidden
        add_to_cart_stepper_type=$add_to_cart_stepper_type
        add_to_cart_stepper_style=$add_to_cart_stepper_style
        product_total_in_cart=$product_total_in_cart
        show_in_cart_with_quantity=$show_in_cart_with_quantity
        show_quantity=$show_quantity
        quick_view_url=$quick_view_url
        config=$config
    }
{/if}

<a class="ty-btn ty-btn__primary ty-btn__big ty-btn__add-to-cart cm-dialog-opener cm-dialog-auto-size ty-quick-view-for-options-link {if $show_add_to_cart_stepper && $show_in_cart_with_quantity && ($product.has_options || $add_to_cart_stepper_cart_aggregate)}hidden{/if} {$quick_view_for_options_link_class}"
    data-ca-view-id="{$product.product_id}_for_options"
    data-ca-target-id="product_quick_view_for_options"
    href="{$quick_view_url|fn_url}"
    data-ca-dialog-title="{__("add_to_cart")}"
    rel="nofollow"
>{include_ext file="common/icon.tpl" class=$quick_view_icon}{$quick_view_text|default:__("add_to_cart")}</a>