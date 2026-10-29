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

$params = $_REQUEST;

/** @var \Tygh\Addons\YandexDelivery\Services\RequestService $request_service */
$request_service = Tygh::$app['addons.yandex_delivery.request_service'];

/** @var \Tygh\Addons\YandexDelivery\Repositories\RequestRepository $request_repository */
$request_repository = Tygh::$app['addons.yandex_delivery.request_repository'];

// These actions call the Yandex API, so neither must be reachable by following a link.
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && ($mode === 'yandex_delivery_label' || $mode === 'yandex_delivery_update_status')
) {
    $shipment_id = ExternalValue::int($params, 'shipment_id');

    try {
        if ($mode === 'yandex_delivery_label') {
            $file_path = $request_service->getShipmentLabelFilePath($shipment_id);
            // On success fn_get_file() sends the file and exits, so reaching the next line means it failed.
            if (!fn_get_file($file_path, 'yandex_delivery_' . $shipment_id . '.pdf')) {
                throw new Exception(__('yandex_delivery.label_unavailable'));
            }
        } else {
            $request_service->updateStatusByShipmentId($shipment_id);
            fn_set_notification(NotificationSeverity::NOTICE, __('notice'), __('yandex_delivery.status_updated'));
        }
    } catch (Exception $e) {
        fn_set_notification(NotificationSeverity::ERROR, __('error'), $e->getMessage());
    }

    return [CONTROLLER_STATUS_OK, 'shipments.details?shipment_id=' . $shipment_id];
}

if ($mode === 'details' && !empty($params['shipment_id'])) {
    $shipment_id = ExternalValue::int($params, 'shipment_id');
    [$shipments,] = fn_get_shipments_info([
        'advanced_info' => true,
        'shipment_id' => $shipment_id,
    ]);
    $shipment = reset($shipments);
    $shipping_info = !empty($shipment['shipping_id'])
        ? fn_get_shipping_info((int) $shipment['shipping_id'], DESCR_SL)
        : [];

    Tygh::$app['view']->assign('yandex_delivery_request', $request_repository->findByShipmentId($shipment_id));
    Tygh::$app['view']->assign(
        'yandex_delivery_is_shipment',
        ($shipment['carrier'] ?? '') === 'yandex_delivery' || ($shipping_info['module'] ?? '') === 'yandex_delivery'
    );
}
