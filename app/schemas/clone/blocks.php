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

use Tygh\Registry;

include_once(Registry::get('config.dir.schemas') . 'clone/blocks.functions.php');

return array(
    'menu' => array(
        'function' => 'fn_ult_clone_layout_block_menu'
    ),
    'products' => array(
        'function' => 'fn_ult_clone_layout_block_configured_by_filling',
        'config' => array(
            'fillings_handlers' => array(
                'manually' => array('fn_ult_clone_layout_block_products_filling_by_manually'),
                'newest' => array('fn_ult_clone_layout_block_products_filling_by_category'),
                'most_popular' => array('fn_ult_clone_layout_block_products_filling_by_category'),
            )
        )
    ),
    'categories' => array(
        'function' => 'fn_ult_clone_layout_block_configured_by_filling',
        'config' => array(
            'fillings_handlers' => array(
                'full_tree_cat' => array('fn_ult_clone_layout_block_categories_filling_by_full_tree_cat'),
                'manually' => array('fn_ult_clone_layout_block_categories_filling_by_manually'),
            )
        )
    ),
    'pages' => array(
        'function' => 'fn_ult_clone_layout_block_configured_by_filling',
        'config' => array(
            'fillings_handlers' => array(
                'full_tree_pages' => array('fn_ult_clone_layout_block_pages_filling_by_tree'),
                'dynamic_tree_pages' => array('fn_ult_clone_layout_block_pages_filling_by_tree'),
                'manually' => array('fn_ult_clone_layout_block_pages_filling_by_manually'),
            )
        )
    ),
    'product_filters' => array(
        'function' => 'fn_ult_clone_layout_block_configured_by_filling',
        'config' => array(
            'fillings_handlers' => array(
                'manually' => array('fn_ult_clone_layout_block_product_filters_filling_by_manually')
            )
        )
    ),
    'product_filters_home' => array(
        'function' => 'fn_ult_clone_layout_block_configured_by_filling',
        'config' => array(
            'fillings_handlers' => array(
                'manually' => array('fn_ult_clone_layout_block_product_filters_filling_by_manually')
            )
        )
    ),
);