{if $product.variation_features_variants && $add_to_cart_stepper_type === "quick_view_for_options_link"}{strip}
    {if !isset($add_to_cart_stepper_cart_product_ids)}
        {$add_to_cart_stepper_cart_product_ids = []}
    {/if}

    {foreach $product.variation_features_variants as $product_variation_features_variants_item}
        {if $product_variation_features_variants_item.purpose === ("\Tygh\Addons\ProductVariations\Product\FeaturePurposes::CREATE_VARIATION_OF_CATALOG_ITEM"|constant) && isset($product_variation_features_variants_item.variants)}
            {foreach $product_variation_features_variants_item.variants as $variant_item}
                {if $variant_item.product_id}
                    {$variant_item_product_id_int = $variant_item.product_id|intval}
                    {$add_to_cart_stepper_cart_product_ids[$variant_item_product_id_int] = $variant_item_product_id_int}
                {/if}
            {/foreach}
        {/if}
    {/foreach}

    {if $add_to_cart_stepper_cart_product_ids|@count > 0}
        {$add_to_cart_stepper_cart_aggregate = true}
    {/if}

    {* Export *}
    {$add_to_cart_stepper_cart_product_ids = $add_to_cart_stepper_cart_product_ids scope=parent}
    {if $add_to_cart_stepper_cart_product_ids|@count > 0}
        {$add_to_cart_stepper_cart_aggregate = $add_to_cart_stepper_cart_aggregate scope=parent}
    {/if}
{/strip}{/if}