{if $settings.Thumbnails.product_details_thumbnail_width && !$product_details_thumbnail_width}
    {$product_details_thumbnail_width = $settings.Thumbnails.product_details_thumbnail_width|intval + 100}
{/if}
{if $settings.Thumbnails.product_details_thumbnail_height && !$product_details_thumbnail_height}
    {$product_details_thumbnail_height = $settings.Thumbnails.product_details_thumbnail_height|intval + 100}
{/if}
{if $product_details_thumbnail_height}
    {$image_panel_image_height = $product_details_thumbnail_height}
{elseif $product_details_thumbnail_width}
    {$image_panel_image_height = $product_details_thumbnail_width}
{elseif $settings.Thumbnails.product_details_thumbnail_height}
    {$image_panel_image_height = $settings.Thumbnails.product_details_thumbnail_height}
{elseif $settings.Thumbnails.product_details_thumbnail_width}
    {$image_panel_image_height = $settings.Thumbnails.product_details_thumbnail_width}
{/if}

{include file="blocks/product_templates/default_template.tpl"
    blocks=$blocks
    tabs_block_id=$tabs_block_id
    product=$product
    product_details_thumbnail_width=$product_details_thumbnail_width
    product_details_thumbnail_height=$product_details_thumbnail_height
    thumbnails_size=$thumbnails_size
    image_panel_image_height=$settings.Thumbnails.product_details_thumbnail_width
    no_images=$no_images
    hide_title=$hide_title
    show_descr=$show_descr
    show_details_button=$show_details_button
    show_product_tabs=$show_product_tabs
    settings=$settings
    smarty=$smarty
}
