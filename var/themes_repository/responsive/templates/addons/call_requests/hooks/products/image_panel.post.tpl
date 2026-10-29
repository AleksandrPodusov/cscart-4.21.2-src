{strip}{if $show_buy_now_on_image_panel}
    {* See addons/master_products/hooks/products/product_data_content.post.tpl *}
    {if !$product.company_id && $show_view_offers_btn && !$is_allow_add_common_products_to_cart_list && (!$details_page || $quick_view)}
        {* skip *}
    {else}
        {if !isset($show_buy_now) && isset($show_add_to_cart_secondary) && $show_add_to_cart_secondary === false}
            {$show_buy_now = false}
        {/if}
        {if !$hide_form
            && $addons.call_requests.buy_now_with_one_click == "YesNo::YES"|enum
            && ($auth.user_id
                || $settings.Checkout.allow_anonymous_shopping == "allow_shopping"
            ) && $show_buy_now|default:true
        }
            {$is_not_required_option = true}
            {foreach $product.product_options as $option}
                {if $option.required === "YesNo::YES"|enum}
                    {$is_not_required_option = false}
                    {break}
                {/if}
            {/foreach}
            {if (
                $settings.General.inventory_tracking == "YesNo::NO"|enum
                || $settings.General.allow_negative_amount == "YesNo::YES"|enum
                || (
                    $product_amount > 0
                    && $product_amount >= $product.min_qty
                )
                || $product.tracking == "ProductTracking::DO_NOT_TRACK"|enum
                || $product.is_edp == "YesNo::YES"|enum
                || $product.out_of_stock_actions == "OutOfStockActions::BUY_IN_ADVANCE"|enum
            )}
                {$image_panel_call_requests_product_id = $product.product_id}

                {hook name="call_requests:call_request_button_image_panel"}
                    {$image_panel_call_requests = [
                        href => "products.view?product_id=`$image_panel_call_requests_product_id`",
                        insert_after_id => "add_to_compare",
                        id => "buy_now_with_one_click_`$obj_prefix``$image_panel_call_requests_product_id`",
                        class => "ty-btn ty-btn__tertiary ty-btn-icon ty-cr-product-button-image-panel",
                        title => __("call_requests.buy_now_with_one_click"),
                        icon => "ty-icon-shopping-bag-speed"
                    ]}

                    {$image_panel_call_requests_popup_target_id = "content_`$image_panel_call_requests.id`"}
                    {$image_panel_call_requests_popup_dialog_title = $image_panel_call_requests.title}
                    {capture name="image_panel_content"}
                        <div class="hidden" id="{$image_panel_call_requests_popup_target_id}" title="{$image_panel_call_requests_popup_dialog_title}"></div>
                    {/capture}
                    {$image_panel_call_requests_popup_override = [
                        href => "call_requests.request?product_id=`$image_panel_call_requests_product_id`&obj_prefix=`$obj_prefix`",
                        id => "opener_`$image_panel_call_requests.id`",
                        class => "`$image_panel_call_requests.class` cm-dialog-opener cm-dialog-auto-size cm-dialog-destroy-on-close",
                        element_attrs => [
                            rel => "nofollow",
                            "data-ca-target-id" => $image_panel_call_requests_popup_target_id,
                            "data-ca-dialog-title" => $image_panel_call_requests_popup_dialog_title,
                            "data-ca-product-id" => $image_panel_call_requests_product_id,
                            "data-ca-dialog-purpose" => "call_request"
                        ],
                        content => $smarty.capture.image_panel_content
                    ]}

                    {if $show_product_options || ($is_not_required_option || $details_page)}
                        {$image_panel_items.top_right.call_requests = array_merge($image_panel_call_requests, $image_panel_call_requests_popup_override)}
                    {else}
                        {$image_panel_items.top_right.call_requests = $image_panel_call_requests}
                    {/if}
                {/hook}
            {/if}
        {/if}

        {$image_panel_items = $image_panel_items scope=parent}
    {/if}
{/if}{/strip}