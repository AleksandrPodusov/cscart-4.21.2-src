<?php
/***************************************************************************
*                                                                          *
*   © 2012 ООО "Эком Системы"                                              *
*                                                                          *
* Это коммерческое программное обеспечение. Только пользователи, которые   *
* приобрели действующую лицензию и согласились с условиями лицензионного   *
* соглашения, могут устанавливать и использовать эту программу.            *
*                                                                          *
****************************************************************************
* ПОЖАЛУЙСТА, ВНИМАТЕЛЬНО ПРОЧТИТЕ ПОЛНЫЙ ТЕКСТ ЛИЦЕНЗИОННОГО СОГЛАШЕНИЯ   *
* В ФАЙЛЕ "copyright.txt", ПРЕДОСТАВЛЕННОМ ВМЕСТЕ С ЭТИМ ДИСТРИБУТИВОМ.    *
***************************************************************************/

use Tygh\Enum\ObjectStatuses;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

function fn_catalog_mode_enabled()
{
    return Registry::get('addons.catalog_mode.main_store_mode') == 'catalog' ?  'Y' : 'N';
}

function fn_catalog_mode_may_disable_minicart()
{
    if (Registry::get('addons.catalog_mode.main_store_mode') == 'store' || Registry::get('addons.catalog_mode.add_to_cart_empty_buy_now_url') == 'Y') {
        return 'N';
    }

    return 'Y';
}

function fn_is_add_to_cart_allowed($product_id)
{
    // No need to involve heavy SQL requests performed by fn_get_products()
    if (Registry::get('addons.catalog_mode.add_to_cart_empty_buy_now_url') == 'Y' && db_get_field("SELECT buy_now_url FROM ?:products WHERE product_id = ?i", $product_id) == '') {
        return true;
    }

    return false;
}

function fn_catalog_mode_pre_add_to_cart(&$product_data, &$cart, &$auth, &$update)
{
    if (AREA == 'C') {
        // Firebug protection
        foreach ($product_data as $key => &$product) {
            $product_id = (!empty($product['product_id'])) ? $product['product_id'] : $key;

            $can_delete = true;

            /**
             * Allows to skip clearing the cart when the catalog mode is enabled
             *
             * @param array $product_data List of products data
             * @param array $cart         Array of cart content and user information necessary for purchase
             * @param array $auth         Array of user authentication data (e.g. uid, usergroup_ids, etc.)
             * @param bool  $update       Flag, if true that is update mode. Usable for order management
             * @param bool  $can_delete   Flag, if true that is cart cleared. Usable to pay off the vendor debt.
             */
            fn_set_hook("catalog_mode_pre_add_to_cart", $product_data, $cart, $auth, $update, $can_delete);

            if (fn_catalog_mode_enabled() == 'Y' && !fn_is_add_to_cart_allowed($product_id) && $can_delete) {
                $product = array();
            }
        }
    }
}

function fn_catalog_mode_update_product_post(&$product_data, &$product_id, &$lang_code, &$create)
{
    if (!empty($product_data['buy_now_url']) && !floatval($product_data['price']) && !empty($product_data['zero_price_action']) && $product_data['zero_price_action'] == 'R') {
        fn_set_notification('N', __('notice'), __('text_catalog_mode_zero_price_action_notice'));
    }
}

/**
 * The "storefront_rest_api_gather_additional_products_data_pre" hook handler.
 *
 * Actions performed:
 * - Loads sellers data for products when requested via API.
 *
 * @param int                                                                     $storefront_id Storefront identifier
 * @param array{properties?: array{addons?: array<string|int|array<string|int>>}} $storefront    Storefront information
 *
 * @param-out array<string, mixed> $storefront Updated storefront info
 *
 * @return void
 */
function fn_catalog_mode_storefront_rest_api_get_storefront($storefront_id, array &$storefront)
{
    $catalog_mode = [
        'is_enabled'      => Registry::get('addons.catalog_mode.status') === ObjectStatuses::ACTIVE,
        'main_store_mode' => Registry::get('addons.catalog_mode.main_store_mode'),
    ];

    if (
        isset($storefront['properties']['addons'])
        && is_array($storefront['properties']['addons'])
    ) {
        $storefront['properties']['addons']['catalog_mode'] = $catalog_mode;
    } else {
        $storefront['properties']['addons'] = [
            'catalog_mode' => $catalog_mode,
        ];
    }
}
