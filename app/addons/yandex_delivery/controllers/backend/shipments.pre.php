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

// Deleting a shipment calls the delivery off, so it must not be reachable by following a link.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return [CONTROLLER_STATUS_OK];
}

if (($mode !== 'delete' && $mode !== 'm_delete') || empty($_REQUEST['shipment_ids'])) {
    return [CONTROLLER_STATUS_OK];
}

/** @var \Tygh\Addons\YandexDelivery\Services\RequestService $request_service */
$request_service = Tygh::$app['addons.yandex_delivery.request_service'];

$cancelled = [];

foreach ((array) $_REQUEST['shipment_ids'] as $shipment_id) {
    if (!is_scalar($shipment_id)) {
        continue;
    }

    try {
        if ($request_service->cancelByShipmentId((int) $shipment_id)) {
            $cancelled[] = (int) $shipment_id;
        }
    } catch (Exception $e) {
        // Removing the shipment while Yandex still carries the parcel would hide a delivery nobody stopped.
        fn_set_notification(
            NotificationSeverity::ERROR,
            __('error'),
            __('yandex_delivery.cancel_failed_shipment_kept', ['[error]' => $e->getMessage()])
        );

        // Deliveries called off before the failure stay called off, and nothing here can bring them back.
        if ($cancelled !== []) {
            fn_set_notification(
                NotificationSeverity::WARNING,
                __('warning'),
                __('yandex_delivery.cancel_already_done', ['[shipments]' => implode(', ', $cancelled)])
            );
        }

        $return_url = empty($_REQUEST['redirect_url']) ? 'shipments.manage' : $_REQUEST['redirect_url'];

        return [CONTROLLER_STATUS_REDIRECT, $return_url];
    }
}

return [CONTROLLER_STATUS_OK];
