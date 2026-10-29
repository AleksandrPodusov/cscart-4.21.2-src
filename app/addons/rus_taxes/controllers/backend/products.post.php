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

use Tygh\Enum\FiscalData1214Objects;
use Tygh\Enum\YesNo;
use Tygh\Registry;

defined('BOOTSTRAP') or die('Access denied');

if ($mode === 'manage' || $mode === 'update' || $mode === 'add' || $mode === 'm_update') {
    $view = Tygh::$app['view'];

    $view->assign('fiscal_data_1212_objects', fn_get_schema('digital_marking', 'fiscal_data_1212_names'));

    $show_marketplace_fee_fiscal_settings = false;

    if ($mode === 'update') {
        $product_data = $view->getTemplateVars('product_data');

        if (
            !empty($product_data['product_id'])
            && fn_rus_taxes_is_marketplace_fee_product($product_data['product_id'])
        ) {
            $show_marketplace_fee_fiscal_settings = true;

            $view->assign(
                'rus_taxes_marketplace_fee',
                [
                    'send_receipt'     => Registry::ifGet('addons.rus_taxes.marketplace_fee_send_receipt', YesNo::NO),
                    'fiscal_data_1212' => fn_rus_taxes_normalize_marketplace_fee_fiscal_data_1212($product_data['fiscal_data_1212'] ?? null),
                    'fiscal_data_1214' => fn_rus_taxes_normalize_marketplace_fee_fiscal_data_1214(
                        Registry::ifGet('addons.rus_taxes.marketplace_fee_fiscal_data_1214', FiscalData1214Objects::FULL_PAYMENT)
                    ),
                ]
            );
            $view->assign('marketplace_fee_fiscal_1212_groups', fn_get_schema('rus_taxes', 'marketplace_fee_fiscal_data_1212_groups'));
            $view->assign('marketplace_fee_fiscal_1214_groups', fn_get_schema('rus_taxes', 'marketplace_fee_fiscal_data_1214_groups'));
            $view->assign('marketplace_fee_fiscal_data_1212_objects', fn_get_schema('rus_taxes', 'marketplace_fee_fiscal_data_1212_names'));
            $view->assign('marketplace_fee_fiscal_data_1214_objects', fn_get_schema('rus_taxes', 'marketplace_fee_fiscal_data_1214_names'));
        }
    }

    $view->assign('rus_taxes_show_marketplace_fee_fiscal_settings', $show_marketplace_fee_fiscal_settings);

    if ($mode === 'update' || $mode === 'add') {
        Registry::set(
            'navigation.tabs.online_cash_registers',
            [
                'title' => __('online_cash_registers'),
                'js'    => true,
            ]
        );
    }

    if ($mode === 'm_update') {
        $field_names = $view->getTemplateVars('field_names');

        if (isset($field_names['fiscal_data_1212'])) {
            $field_names['fiscal_data_1212'] = __('payment_object');
        }

        if (isset($field_names['mark_code_type'])) {
            $field_names['mark_code_type'] = __('is_fur_ware');
        }

        $view->assign('field_names', $field_names);
    }
}
