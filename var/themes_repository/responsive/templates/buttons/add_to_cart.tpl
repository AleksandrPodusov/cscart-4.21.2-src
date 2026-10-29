{hook name="buttons:add_to_cart"}
    {$show_add_to_cart_stepper = $show_add_to_cart_stepper|default:$theme_manifest_features.add_to_cart_stepper|default:false}
    {if $show_add_to_cart_stepper}
        {$enable_add_to_cart_form_dialog_closer = false}
    {/if}
    {$enable_add_to_cart_form_dialog_closer = $enable_add_to_cart_form_dialog_closer|default:true}
    {assign var="c_url" value=$config.current_url|escape:url}
    {if $settings.Checkout.allow_anonymous_shopping == "allow_shopping" || $auth.user_id}
        {if $show_add_to_cart_stepper}
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
                config=$config
            }
        {/if}
        {if $enable_add_to_cart_form_dialog_closer}
            {$add_to_cart_meta = "`$add_to_cart_meta` cm-form-dialog-closer"}
        {/if}
        {include file="buttons/button.tpl" but_id=$but_id but_text=$but_text|default:__("add_to_cart") but_name=$but_name but_onclick=$but_onclick but_href=$but_href but_target=$but_target but_role=$but_role|default:"text" but_meta="ty-btn__primary ty-btn__big ty-btn__add-to-cart `$add_to_cart_meta`" but_icon=$but_icon}
    {else}

        {if $runtime.controller == "auth" && $runtime.mode == "login_form"}
            {assign var="login_url" value=$config.current_url}
        {else}
            {assign var="login_url" value="auth.login_form?return_url=`$c_url`"}
        {/if}

        {include file="buttons/button.tpl" but_id=$but_id but_text=__("sign_in_to_buy") but_href=$login_url but_role=$but_role|default:"text" but_name="" but_icon=$but_icon}
        <p class="ty-add-to-cart__text-login-to-add-to-cart">{__("text_login_to_add_to_cart") nofilter}</p>
    {/if}
{/hook}
{* Export *}
{* Change the Buy now button behavior using a hook *}
{$show_buy_now = $show_buy_now scope = parent}
{$quick_view_for_options_link_class = $quick_view_for_options_link_class scope=parent}
