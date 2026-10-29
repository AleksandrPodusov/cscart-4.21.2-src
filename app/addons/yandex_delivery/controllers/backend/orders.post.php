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

use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Enum\NotificationSeverity;

defined('BOOTSTRAP') or die('Access denied');

/** @var array $params */
$params = $_REQUEST;

/** @var \Tygh\Addons\YandexDelivery\Services\RequestService $request_service */
$request_service = Tygh::$app['addons.yandex_delivery.request_service'];

/** @var \Tygh\Addons\YandexDelivery\Repositories\RequestRepository $request_repository */
$request_repository = Tygh::$app['addons.yandex_delivery.request_repository'];

$yandex_delivery_modes = [
    'yandex_delivery_labels',
    'yandex_delivery_create_request',
    'yandex_delivery_update_status',
];

// These actions call the Yandex API, so none of them must be reachable by following a link.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_array($mode, $yandex_delivery_modes, true)) {
    $order_id = ExternalValue::int($params, 'order_id');
    $shipment_id = ExternalValue::int($params, 'shipment_id');

    try {
        if ($mode === 'yandex_delivery_labels') {
            $file_path = $request_service->getOrderLabelsFilePath($order_id);
            // On success fn_get_file() sends the file and exits, so reaching the next line means it failed.
            if (!fn_get_file($file_path, 'yandex_delivery_order_' . $order_id . '.pdf')) {
                throw new Exception(__('yandex_delivery.label_unavailable'));
            }
        } elseif ($mode === 'yandex_delivery_create_request') {
            $yandex_request = $request_service->createRequestByShipmentId($shipment_id);
            fn_set_notification(
                NotificationSeverity::NOTICE,
                __('notice'),
                __('yandex_delivery.request_created', ['[request_id]' => $yandex_request['request_id']])
            );
        } else {
            $request_service->updateStatusByShipmentId($shipment_id);
            fn_set_notification(NotificationSeverity::NOTICE, __('notice'), __('yandex_delivery.status_updated'));
        }
    } catch (Exception $e) {
        fn_set_notification(NotificationSeverity::ERROR, __('error'), $e->getMessage());
    }

    return [CONTROLLER_STATUS_OK, 'orders.details?order_id=' . $order_id];
}

if ($mode === 'details' && !empty($params['order_id'])) {
    $order_id = ExternalValue::int($params, 'order_id');

    Tygh::$app['view']->assign('yandex_delivery_requests', $request_repository->findByOrderId($order_id));
    Tygh::$app['view']->assign(
        'yandex_delivery_has_labels',
        !empty($request_repository->findCreatedByOrderId($order_id))
    );
}
