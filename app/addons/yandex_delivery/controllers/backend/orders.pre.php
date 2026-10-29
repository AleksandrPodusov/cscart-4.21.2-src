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

use Tygh\Enum\NotificationSeverity;

defined('BOOTSTRAP') or die('Access denied');

/** @var string $mode */

// Deleting an order calls its deliveries off, so it must not be reachable by following a link.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return [CONTROLLER_STATUS_OK];
}

if ($mode === 'delete') {
    $order_ids = empty($_REQUEST['order_id']) ? [] : [$_REQUEST['order_id']];
} elseif ($mode === 'm_delete') {
    $order_ids = empty($_REQUEST['order_ids']) ? [] : (array) $_REQUEST['order_ids'];
} else {
    return [CONTROLLER_STATUS_OK];
}

/** @var \Tygh\Addons\YandexDelivery\Repositories\RequestRepository $request_repository */
$request_repository = Tygh::$app['addons.yandex_delivery.request_repository'];

/** @var \Tygh\Addons\YandexDelivery\Services\RequestService $request_service */
$request_service = Tygh::$app['addons.yandex_delivery.request_service'];

$cancelled = [];

foreach ($order_ids as $order_id) {
    if (!is_scalar($order_id)) {
        continue;
    }

    $shipment_ids = array_keys($request_repository->findCreatedByOrderId((int) $order_id));

    foreach ($shipment_ids as $shipment_id) {
        try {
            if ($request_service->cancelByShipmentId($shipment_id)) {
                $cancelled[] = $shipment_id;
            }
        } catch (Exception $e) {
            // Removing the order while Yandex still carries a parcel would hide a delivery nobody stopped.
            fn_set_notification(
                NotificationSeverity::ERROR,
                __('error'),
                __('yandex_delivery.cancel_failed_order_kept', ['[error]' => $e->getMessage()])
            );

            // Deliveries called off before the failure stay called off, and nothing here can bring them back.
            if ($cancelled !== []) {
                fn_set_notification(
                    NotificationSeverity::WARNING,
                    __('warning'),
                    __('yandex_delivery.cancel_already_done', ['[shipments]' => implode(', ', $cancelled)])
                );
            }

            return [CONTROLLER_STATUS_REDIRECT, 'orders.manage'];
        }
    }
}

return [CONTROLLER_STATUS_OK];
