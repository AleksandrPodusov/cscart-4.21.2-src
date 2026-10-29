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

namespace Tygh\Addons\RusSdek2\Services;

use Tygh\Addons\RusSdek2\Shippings\SdekApiClient;
use Tygh\Database\Connection;
use Tygh\Enum\NotificationSeverity;
use Tygh\Navigation\LastView;
use Tygh\Registry;
use Tygh\Shippings\Shippings;

class SdekService
{
    /** @var Connection */
    protected $db;

    /**
     * @param Connection $db Database
     *
     * @return void
     */
    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Checks if the data about shipment statuses exists.
     *
     * @return bool Returns true if the data about shipment statuses exists; returns false otherwise
     */
    public function deliveryCheckOrders()
    {
        $status_id = $this->db->getField('SELECT status_id FROM ?:rus_sdek2_history_status LIMIT 1');

        return !empty($status_id);
    }



    /**
     * Gets the data of specific shipment statuses; the passed parameters determine,
     * the data of which statuses will be returned.
     *
     * @param array $params         The parameters by which the shipment statuses will be selected
     * @param int   $items_per_page The maximum number of statuses to appear on one page
     *
     * @return array Returns an array of shipment statuses, and the parameters of the selected
     *        statuses
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getStatus(array $params = [], $items_per_page = 0)
    {
        $condition = 'WHERE 1 ';
        $_view = 'sdek_status';
        $params = LastView::instance()->update($_view, $params);

        $default_params = [
            'page' => 1,
            'items_per_page' => $items_per_page
        ];

        $params = array_merge($default_params, $params);

        if (!empty($params['time_from'])) {
            $condition .= $this->db->quote(' AND timestamp >= ?i', strtotime($params['time_from']));

            if (!empty($params['time_to'])) {
                $condition .= $this->db->quote(' AND timestamp < ?i', strtotime($params['time_to']));
            }
        }

        if (!empty($params['sdek_order_id'])) {
            $condition .= $this->db->quote(' AND order_id = ?i ', $params['sdek_order_id']);
        }

        $sort_by = !empty($params['sort_by']) ? $params['sort_by'] : 'order_id';
        $sort = 'asc';
        if (!empty($params['sort_order'])) {
            $sort = $params['sort_order'];
            $params['sort_order'] = ($params['sort_order'] === 'asc') ? 'desc' : 'asc';
            $params['sort_order_rev'] = $params['sort_order'];
        } else {
            $params['sort_order'] = 'asc';
            $params['sort_order_rev'] = 'asc';
        }

        $limit = '';
        if (!empty($params['items_per_page'])) {
            $params['total_items'] = $this->db->getField('SELECT COUNT(*) FROM ?:rus_sdek2_history_status ?p', $condition);
            $limit = db_paginate($params['page'], $params['items_per_page']);
        }

        $data_status = $this->db->getArray(
            'SELECT * FROM ?:rus_sdek2_history_status ?p ORDER BY ?p ?p ?p',
            $condition,
            $sort_by,
            $sort,
            $limit
        );

        return [$data_status, $params];
    }

    /**
     * Sets the weight of a product/order to 100 grams in the specified weight measurement unit
     * when the product/order doesn't have weight.
     *
     * @param float|int $weight       The weight of a product or order.
     * @param float|int $symbol_grams Grams in the unit of weight defined by the weight symbol.
     *
     * @return float|int The weight of a product or order.
     */
    public function checkWeight($weight, $symbol_grams)
    {
        //phpcs:ignore
        if (empty($weight) || $weight == 0) {
            $weight = (100 / $symbol_grams);
        }

        return $weight;
    }

    /**
     * Calculates shipping costs for SDEK.
     *
     * @param array $order_info    The array with the order data
     * @param array $shipping_info The array with the shipping method data
     * @param array $shipment_info The array with the shipment data
     *
     * @return int
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function calculateCostByShipment(array $order_info, array $shipping_info, array $shipment_info)
    {
        $length = $width = $height = SDEK2_DEFAULT_DIMENSIONS;

        $shipping_info['module'] = $shipment_info['carrier'];

        $symbol_grams = Registry::get('settings.General.weight_symbol_grams');

        $products = [];

        foreach ($shipment_info['products'] as $item_id => $amount) {
            $product = $order_info['products'][$item_id];

            $product_extra = $this->db->getRow('SELECT shipping_params, weight FROM ?:products WHERE product_id = ?i', $product['product_id']);

            $product_weight = $this->checkWeight($product_extra['weight'], $symbol_grams);

            $p_ship_params = unserialize($product_extra['shipping_params']);

            $package_length = empty($p_ship_params['box_length']) ? $length : $p_ship_params['box_length'];
            $package_width = empty($p_ship_params['box_width']) ? $width : $p_ship_params['box_width'];
            $package_height = empty($p_ship_params['box_height']) ? $height : $p_ship_params['box_height'];
            $weight_ar = fn_convert_weight_to_imperial_units($product_weight);
            $weight = $weight_ar['plain'];

            $params_product = [
                'weight' => $weight,
                'length' => $package_length,
                'width' => $package_width,
                'height' => $package_height
            ];

            foreach ($order_info['product_groups'] as $product_groups) {
                if (!empty($product_groups['products'][$item_id])) {
                    $products[$item_id] = $product_groups['products'][$item_id];
                    $products[$item_id] = array_merge($products[$item_id], $params_product);
                    $products[$item_id]['amount'] = $amount;
                }

                $shipping_info['package_info'] = $product_groups['package_info'];
            }
        }

        $data_package = Shippings::groupProductsList($products, $shipping_info['package_info']['location']);
        $data_package = reset($data_package);
        $shipping_info['package_info_full'] = $data_package['package_info_full'];
        $shipping_info['package_info'] = $data_package['package_info_full'];

        $sum_rate = Shippings::calculateRates([$shipping_info]);
        $sum_rate = reset($sum_rate);

        return $sum_rate['price'];
    }

    /**
     * Gets order status from SDEK.
     *
     * @param array $shipping_params Shipping params
     * @param int   $order_id        Order ID
     * @param int   $shipment_id     Shipment ID
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function orderStatus(array $shipping_params, $order_id = 0, $shipment_id = 0)
    {
        if (empty($shipment_id)) {
            return [];
        }

        [$shipments,] = fn_get_shipments_info(['order_id' => $order_id, 'advanced_info' => true]);
        $shipment = reset($shipments);

        $data_status = [];

        $order_uuid = $this->getSdekOrderUUIDByTrackingNumber($shipment['tracking_number']);

        if (!empty($order_uuid)) {
            try {
                $client_sdek = new SdekApiClient($shipping_params);

                $response = $client_sdek->getOrder($order_uuid);

                if (!empty($response)) {
                    $order = explode('_', $response->number);

                    $order_shipment_id = isset($order[1])
                        ? (int) $order[1]
                        : null;

                    if ($order_shipment_id) {
                        foreach ($response->statuses as $status) {
                            $data_status[$order[0] . '_' . $order[1] . '_' . $status->code] = [
                                'status_id'   => $status->code,
                                'order_id'    => $order[0],
                                'shipment_id' => $order[1],
                                'timestamp'   => strtotime($status->date_time),
                                'status'      => $status->name,
                                'city'        => $status->city,
                                // phpcs:ignore
                                'date'        => date("d-m-Y", strtotime($status->date_time)),
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                fn_set_notification(NotificationSeverity::ERROR, __('error'), $e->getMessage());
            }
        }

        return $data_status;
    }

    /**
     * Update SDEK order statuses.
     *
     * @param array $date_status Status data
     *
     * @return void|bool
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function addStatusOrders(array $date_status)
    {
        if (empty($date_status)) {
            return false;
        }

        $sdek_history = [];
        $_data_status = $this->db->getArray('SELECT * FROM ?:rus_sdek2_status');
        foreach ($_data_status as $_status) {
            $id_history = $_status['order_id'] . '_' . $_status['shipment_id'];

            $n_status = [
                'status_id' => $_status['status_id'],
                'order_id' => $_status['order_id'],
                'shipment_id' => $_status['shipment_id'],
                'timestamp' => $_status['timestamp'],
                'status' => $_status['status'],
                'city' => $_status['city'],
                'id' => $_status['id']
            ];

            if (!empty($sdek_history[$id_history])) {
                if ($sdek_history[$id_history]['timestamp'] < $_status['timestamp']) {
                    $sdek_history[$id_history] = $n_status;
                }
            } else {
                $sdek_history[$id_history] = $n_status;
            }
        }

        $new_statuses = [];
        foreach ($date_status as $d_status) {
            $status_id = $this->db->getRow(
                'SELECT id FROM ?:rus_sdek2_status'
                . ' WHERE status_id = ?i and order_id = ?i and shipment_id = ?i ',
                $d_status['status_id'],
                $d_status['order_id'],
                $d_status['shipment_id']
            );

            $n_status = [
                'status_id' => $d_status['status_id'],
                'order_id' => $d_status['order_id'],
                'shipment_id' => $d_status['shipment_id'],
                'timestamp' => $d_status['timestamp'],
                'status' => $d_status['status'],
                'city' => $d_status['city']
            ];

            if (empty($status_id)) {
                $new_statuses[] = $n_status;
            }

            $n_status['id'] = empty($d_status['id']) ? '' : $d_status['status_id'];

            $id_history = $n_status['order_id'] . '_' . $n_status['shipment_id'];

            if (!empty($sdek_history[$id_history])) {
                if ($sdek_history[$id_history]['timestamp'] < $n_status['timestamp']) {
                    $sdek_history[$id_history] = $n_status;
                }
            } else {
                $sdek_history[$id_history] = $n_status;
            }
        }

        if (!empty($new_statuses)) {
            $this->db->query('INSERT INTO ?:rus_sdek2_status ?m', $new_statuses);
        }

        $d_status_ids = $this->db->getMultiHash(
            'SELECT order_id, shipment_id, id'
            . ' FROM ?:rus_sdek2_history_status',
            ['order_id', 'shipment_id']
        );

        foreach ($sdek_history as $k_history => $_history) {
            $sdek_history[$k_history]['id'] = '';

            if (!empty($d_status_ids[$_history['order_id']][$_history['shipment_id']]['id'])) {
                $sdek_history[$k_history]['id'] = $d_status_ids[$_history['order_id']][$_history['shipment_id']]['id'];
            }
        }

        if (!empty($sdek_history)) {
            $this->db->query('REPLACE INTO ?:rus_sdek2_history_status ?m', $sdek_history);
        }
    }

    /**
     * Prepares array of product params from shipping information.
     * Used for shipping cost calculation and filtering.
     *
     * @param array $shipping_info The array with the shipping data
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function prepareProductParamsByShippingInfo(array $shipping_info)
    {
        $symbol_grams = Registry::get('settings.General.weight_symbol_grams');
        $weight_data = fn_convert_weight_to_imperial_units($shipping_info['package_info']['W']);
        $shipping_settings = $shipping_info['service_params'];

        $weight = $weight_data['plain'] * $symbol_grams;

        $sdek_default_dimensions = defined('SDEK2_DEFAULT_DIMENSIONS') ? SDEK2_DEFAULT_DIMENSIONS : 0;
        $length = !empty($shipping_settings['length']) ? $shipping_settings['length'] : $sdek_default_dimensions;
        $width = !empty($shipping_settings['width']) ? $shipping_settings['width'] : $sdek_default_dimensions;
        $height = !empty($shipping_settings['height']) ? $shipping_settings['height'] : $sdek_default_dimensions;

        $params_product = [];
        if (!empty($shipping_info['package_info']['packages'])) {
            $packages = $shipping_info['package_info']['packages'];
            $packages_count = count($packages);

            if ($packages_count > 0) {
                foreach ($packages as $id => $package) {
                    $weight_ar = fn_convert_weight_to_imperial_units($package['weight']);

                    if (!empty($_REQUEST['order_id'])) {
                        $weight = 0;

                        foreach ($package['products'] as $cart_id => $amount) {
                            $product_weight = 0;
                            $d_product = db_get_row(
                                'SELECT product_id, extra FROM ?:order_details WHERE item_id = ?i AND order_id = ?i',
                                $cart_id,
                                $_REQUEST['order_id']
                            );
                            $extra = @unserialize($d_product['extra']);

                            if (!empty($extra['product_options_value'])) {
                                $product_options = [];
                                foreach ($extra['product_options_value'] as $_options) {
                                    $product_options[$_options['option_id']] = $_options['value'];
                                }

                                $product_weight = fn_apply_options_modifiers($product_options, $product_weight, 'W');
                            } else {
                                $product_weight = db_get_field(
                                    'SELECT weight FROM ?:products WHERE product_id = ?i',
                                    $d_product['product_id']
                                );
                            }

                            $product_weight = $this->checkWeight($product_weight, $symbol_grams);

                            $product_weight_g = $product_weight * $symbol_grams;

                            $weight += $product_weight_g * $amount;
                        }

                        if (empty($weight) && !empty($weight_ar['plain'])) {
                            $weight = $weight_ar['plain'] * $symbol_grams;
                        } elseif (empty($weight)) {
                            $weight = 100;
                        }
                    } else {
                        $weight = $weight_ar['plain'] * $symbol_grams;
                    }

                    $package_length = empty($package['shipping_params']['box_length']) ? $length : $package['shipping_params']['box_length'];
                    $package_width = empty($package['shipping_params']['box_width']) ? $width : $package['shipping_params']['box_width'];
                    $package_height = empty($package['shipping_params']['box_height']) ? $height : $package['shipping_params']['box_height'];

                    $params_product[$id]['weight'] = $weight;
                    $params_product[$id]['length'] = $package_length;
                    $params_product[$id]['width'] = $package_width;
                    $params_product[$id]['height'] = $package_height;
                }
            } else {
                $params_product['weight'] = $weight;
                $params_product['length'] = $length;
                $params_product['width'] = $width;
                $params_product['height'] = $height;
                $params_product = [$params_product];
            }
        } else {
            $params_product['weight'] = $weight;
            $params_product['length'] = $length;
            $params_product['width'] = $width;
            $params_product['height'] = $height;
            $params_product = [$params_product];
        }

        return $params_product;
    }

    /**
     * Filters SDEK pickup points with 'postamat' type by comparing product physical parameters with postamat parameters.
     *
     * @param \Tygh\Shippings\PickupPoints\Dto\PickupPoint[] $pickup_points  SDEK postamat at destination
     * @param array<array<string, float|int|string>>         $product_params Physical parameters of product
     *
     * @return \Tygh\Shippings\PickupPoints\Dto\PickupPoint[]
     */
    public function filterPostamatPickupPoints(array $pickup_points, array $product_params)
    {
        foreach ($product_params as $product_param) {
            $pickup_points = array_filter($pickup_points, static function ($pickup_point) use ($product_param) {
                $pickup_point_extra_data = $pickup_point->getExtraData();

                if (empty($pickup_point_extra_data)) {
                    return false;
                }

                if (
                    !isset($pickup_point_extra_data['weight_min']) || !isset($pickup_point_extra_data['weight_max'])
                    || (
                        $product_param['weight'] < $pickup_point_extra_data['weight_min'] * 1000
                        || $product_param['weight'] > $pickup_point_extra_data['weight_max'] * 1000
                    )
                ) {
                    return false;
                }
                if (
                    empty($pickup_point_extra_data['dimensions'])
                    || (
                        $product_param['length'] > $pickup_point_extra_data['dimensions']['depth']
                        || $product_param['height'] > $pickup_point_extra_data['dimensions']['height']
                        || $product_param['width'] > $pickup_point_extra_data['dimensions']['width']
                    )
                ) {
                    return false;
                }
                return true;
            });
        }

        return $pickup_points;
    }

    /**
     * Gets SDEK order UUID by tracking number.
     *
     * @param string $tracking_number The weight of a product or order
     *
     * @return string
     */
    public function getSdekOrderUUIDByTrackingNumber($tracking_number)
    {
        return $this->db->getField('SELECT sdek_order_uuid FROM ?:rus_sdek2_register WHERE sdek_number = ?s', $tracking_number);
    }
}
