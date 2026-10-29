{assign var="product_detail_view_url" value="products.view?product_id=`$product.product_id`"}
{$product_image_galleries = [
    swiper => [
        type => "swiper",
        wrapper_outer => "ty-center-block swiper-container",
        image_gallery => "ty-thumbs-wrapper swiper-wrapper",
        gallery_item => "ty-scroller__item swiper-slide",
        lazy_load => false,
        pagination => "swiper-pagination",
        max_items => 6
    ],
    owlcarousel => [
        type => "owlcarousel",
        wrapper_outer => "ty-center-block",
        image_gallery => "ty-thumbs-wrapper ty-scroller owl-carousel",
        gallery_item => "ty-scroller__item",
        lazy_load => true,
        pagination => "",
        max_items => 0
    ]
]}

{* Get params. Example: $product_image_gallery_type *}
{include file="views/products/components/product_icon_pre.tpl"
    product=$product
    obj_id_prefix=$obj_id_prefix
    product_detail_view_url=$product_detail_view_url
    show_gallery=$show_gallery
    settings=$settings
}

{$product_image_gallery_type = $product_image_gallery_type|default:"owlcarousel"}
{$product_image_gallery =  $product_image_galleries[$product_image_gallery_type]}

{capture name="product_detail_view_url"}
{** Sets product detail view link *}
{hook name="products:product_detail_view_url"}
{$product_detail_view_url}
{/hook}
{/capture}

{$product_detail_view_url = $smarty.capture.product_detail_view_url|trim}

{capture name="main_icon"}
    <a href="{"$product_detail_view_url"|fn_url}">
        {include file="common/image.tpl" obj_id=$obj_id_prefix images=$product.main_pair image_width=$settings.Thumbnails.product_lists_thumbnail_width image_height=$settings.Thumbnails.product_lists_thumbnail_height}
    </a>
{/capture}

{if $product.image_pairs && $show_gallery}
<div class="cm-image-gallery-wrapper-outer ty-product-icon {$product_image_gallery.wrapper_outer}">
    <div class="cm-image-gallery {$product_image_gallery.image_gallery}"
        data-ca-items-count="1"
        data-ca-items-responsive="true"
        data-ca-scroller-item="1"
        data-ca-scroller-item-desktop="1"
        data-ca-scroller-item-desktop-small="1"
        data-ca-scroller-item-tablet="1"
        data-ca-scroller-item-mobile="1"
        id="icons_{$obj_id_prefix}">
        {if $product.main_pair}
            <div class="cm-gallery-item cm-item-gallery {$product_image_gallery.gallery_item}">
                {$smarty.capture.main_icon nofilter}
            </div>
        {/if}
        {foreach from=$product.image_pairs item="image_pair"}
            {if $image_pair
                && ((
                    isset($product_image_gallery.max_items)
                    && $product_image_gallery.max_items !== 0
                    && $image_pair@iteration < $product_image_gallery.max_items
                )
                || !isset($product_image_gallery.max_items)
                || $product_image_gallery.max_items === 0
            )}
                <div class="cm-gallery-item cm-item-gallery {$product_image_gallery.gallery_item}">
                    <a href="{"$product_detail_view_url"|fn_url}">
                        {include file="common/image.tpl" no_ids=true images=$image_pair image_width=$settings.Thumbnails.product_lists_thumbnail_width image_height=$settings.Thumbnails.product_lists_thumbnail_height lazy_load=$product_image_gallery.lazy_load}
                    </a>
                </div>
            {/if}
        {/foreach}
    </div>
    {if isset($product_image_gallery.pagination) && $product_image_gallery.pagination !== ""}
        <div class="cm-image-gallery-pagination ty-product-icon__pagination {$product_image_gallery.pagination}"></div>
    {/if}
</div>
{else}
    {$smarty.capture.main_icon nofilter}
{/if}