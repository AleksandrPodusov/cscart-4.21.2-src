{if $show_add_to_cart_stepper}{strip}
    {$config = \Tygh\Registry::get('config')}
    {$settings = \Tygh\Registry::get('settings')}

    {$c_url = $config.current_url|escape:html}
    {$default_minimal_qty = 1}
    {$add_to_cart_stepper_style = $add_to_cart_stepper_style|default:"icon"}
    {$cart_product_qty_step_int = $cart_product.qty_step|intval}
    {$show_quantity = $show_quantity|default:true}
    {$show_in_cart_with_quantity = $show_in_cart_with_quantity|default:false}

    <div {""}
        class="ty-add-to-cart-stepper {if $is_add_to_cart_stepper_hidden}hidden{/if}" {""}
        data-ca-add-to-cart-stepper="main" {""}
    >
        {if $show_quantity}
            <div {""}
                class="ty-add-to-cart-stepper__quantity {""}
                {if $settings.Appearance.quantity_changer === "YesNo::YES"|enum}cm-value-changer{/if} {if $cart_product.is_edp === "YesNo::YES"|enum || $cart_product.exclude_from_calculate}ty-add-to-cart-stepper__quantity--edp{/if}" {""}
                data-ca-add-to-cart-stepper="quantity" {""}
            >
            {* Decrease button (minus) *}
            {if $settings.Appearance.quantity_changer === "YesNo::YES"|enum}
                <a {""} 
                    class="ty-btn ty-btn__tertiary ty-add-to-cart-stepper__decrease cm-decrease" {""}
                    title="{__("decrease_product_quantity")}" {""}
                    data-ca-add-to-cart-stepper="decrease" {""}
                >
                    {include_ext
                        file="common/icon.tpl"
                        class="{($cart_product.is_edp === "YesNo::YES"|enum || $cart_product.exclude_from_calculate) ? "ty-icon-trashcan" : "ty-icon-minus"
                        } ty-add-to-cart-stepper__decrease-icon"
                    }
                </a>
            {/if}

            {* Input quantity *}
            <input {""}
                name="product_data[{$obj_id}][amount_stepper]" {""}
                type="text" {""}
                value="{$cart_product.amount}" {""}
                class="ty-add-to-cart-stepper__input-quantity cm-amount cm-value-decimal {""}
                    {if $cart_product.is_edp === "YesNo::YES"|enum || $cart_product.exclude_from_calculate}ty-add-to-cart-stepper__input-quantity--edp{/if}" {""}
                data-ca-add-to-cart-stepper="inputQuantity" {""}
                data-ca-add-to-cart-stepper-type="{$add_to_cart_stepper_type|default:"basic"}" {""}
                data-ca-add-to-cart-stepper-product-id="{$cart_product.product_id}" {""}
                data-ca-add-to-cart-stepper-cart-product-id="{$cart_product.cart_id}" {""}
                data-ca-add-to-cart-stepper-target-id="cart_status*,cart*,checkout*" {""}
                data-ca-add-to-cart-stepper-checkout-update-product-id-name="cart_products[{$cart_product.cart_id}][product_id]" {""}
                data-ca-add-to-cart-stepper-checkout-update-amount-name="cart_products[{$cart_product.cart_id}][amount]" {""}
                data-ca-add-to-cart-stepper-redirect-url="{$redirect_url|escape:html}" {""}
                data-ca-add-to-cart-stepper-current-url="{$c_url|escape:html}" {""}
                data-ca-add-to-cart-stepper-obj-prefix="{$obj_prefix}" {""}
                data-ca-add-to-cart-stepper-obj-id="{$obj_id}" {""}
                data-ca-add-to-cart-stepper-reload-obj-prefix="{$reload_obj_prefix}" {""}
                data-ca-add-to-cart-stepper-reload-obj-id="{$reload_obj_id}" {""}
                data-ca-min-qty="{if !$cart_product.min_qty}{$default_minimal_qty}{else}{$cart_product.min_qty}{/if}" {""}
                data-ca-value-changer-allow-zero="true" {""}
                {if $cart_product_qty_step_int > 1}
                    data-ca-step="{$cart_product_qty_step_int}" {""}
                {/if}
            />

            {* Increase button (plus) *}
            {if $settings.Appearance.quantity_changer === "YesNo::YES"|enum
                && $cart_product.is_edp !== "YesNo::YES"|enum
                && !$cart_product.exclude_from_calculate
            }
                <a {""}
                    class="ty-btn ty-btn__tertiary ty-add-to-cart-stepper__increase cm-increase" {""}
                    title="{__("increase_product_quantity")}" {""}
                    data-ca-add-to-cart-stepper="increase" {""}
                >
                    {include_ext
                        file="common/icon.tpl"
                        class="ty-icon-plus ty-add-to-cart-stepper__increase-icon"
                    }
                </a>
            {/if}
            </div>
        {/if}

        {if $show_in_cart_with_quantity}
            <a class="ty-btn ty-btn__primary ty-btn__big ty-btn__add-to-cart cm-dialog-opener cm-dialog-auto-size ty-quick-view-for-options-link ty-btn__add-to-cart--in-cart-with-quantity" {""}
                data-ca-view-id="{$cart_product.product_id}_for_options" {""}
                data-ca-target-id="product_quick_view_for_options" {""}
                href="{$quick_view_url|fn_url}" {""}
                data-ca-dialog-title="{__("add_to_cart")}" {""}
                rel="nofollow" {""}
            >
                {__("in_cart_with_quantity", ["[quantity]" => $product_total_in_cart])}
            </a>
        {/if}

        {* In cart button *}
        <a {""}
            href="{"checkout.cart"|fn_url}" {""}
            rel="nofollow" {""}
            class="ty-btn ty-btn__big ty-add-to-cart-stepper__cart {""}
                {if $cart_product.is_edp === "YesNo::YES"|enum || $cart_product.exclude_from_calculate}ty-add-to-cart-stepper__cart--edp{/if}" {""}
        >
            {if $add_to_cart_stepper_style !== "icon"}
                <span class="ty-add-to-cart-stepper__cart-text">
                    {__("in_cart")}
                </span>
            {/if}
            {include_ext
                file="common/icon.tpl"
                class="ty-add-to-cart-stepper__cart-icon ty-add-to-cart-stepper__cart-icon--in-cart ty-icon-ok"
                data=["data-ca-add-to-cart-stepper" => "cartIconInCart"]
            }
            {include_ext
                file="common/icon.tpl"
                class="ty-add-to-cart-stepper__cart-icon ty-add-to-cart-stepper__cart-icon--loading ty-icon-progress-activity hidden"
                data=["data-ca-add-to-cart-stepper" => "cartIconLoading"]
            }
        </a>
    </div>
{/strip}{/if}