{strip}{capture name="image_panel_data"}
{$config = \Tygh\Registry::get('config')}
{$settings = \Tygh\Registry::get('settings')}
{$c_url = $config.current_url|escape:url}
{$product_id = $product.product_id}

{$image_panel_items = [
    top_left => [],
    top_right => [],
    bottom_left => [],
    bottom_right => []
]}

{* Add to compare list *}
{if $show_add_to_compare_list
    && $settings.General.enable_compare_products === "YesNo::YES"|enum
    && !$hide_compare_list_button
    && $show_add_to_compare_list_on_image_panel
}
    {$image_panel_items.top_right.add_to_compare = [
        href => "product_features.add_product?product_id=$product_id&redirect_url=$c_url",
        class => "ty-btn ty-btn__tertiary ty-btn-icon ty-add-to-compare",
        title => __("add_to_comparison_list"),
        icon => "ty-icon-chart-bar",
        ajax => !$config.tweaks.disable_dhtml,
        full_render => !$config.tweaks.disable_dhtml,
        element_attrs => [
            rel => "nofollow",
            "data-ca-target-id" => "comparison_list,account_info*"
        ]
    ]}
{/if}
{* /Add to compare list *}

{hook name="products:image_panel"}{/hook}

{$image_panel_items = $image_panel_items scope=parent}
{/capture}{/strip}