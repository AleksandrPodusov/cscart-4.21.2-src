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

namespace Tygh\Addons\YandexDelivery\HookHandlers;

use Tygh\Addons\YandexDelivery\Repositories\RequestRepository;
use Tygh\Addons\YandexDelivery\Services\RequestService;

/**
 * Keeps local Yandex Delivery request records in sync with shipments.
 */
class ShipmentsHookHandler
{
    /** @var RequestRepository */
    protected $repository;

    /** @var RequestService */
    protected $request_service;

    /**
     * @param RequestRepository $repository      Request repository
     * @param RequestService    $request_service Request service
     *
     * @return void
     */
    public function __construct(RequestRepository $repository, RequestService $request_service)
    {
        $this->repository = $repository;
        $this->request_service = $request_service;
    }

    /**
     * Hands the recipient tracking page to whatever shows a shipment.
     *
     * The core fills `carrier_info.tracking_url` by substituting the tracking number into a template, which suits
     * carriers whose tracking page is addressable by number. Yandex issues a per-request link instead, so it is
     * stored and put in place here. Both the storefront and the shipment e-mail read this same field.
     *
     * @param array<int, array<string, string|int|float|array|null>> $shipments Shipments about to be returned
     * @param array<string, string|int|float|array|null>             $params    Search parameters
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function onGetShipmentsInfoPost(array &$shipments, array $params)
    {
        $shipment_ids = [];

        foreach ($shipments as $shipment) {
            if (!is_array($shipment) || ($shipment['carrier'] ?? '') !== 'yandex_delivery') {
                continue;
            }

            $shipment_id = $shipment['shipment_id'] ?? null;

            if (is_scalar($shipment_id)) {
                $shipment_ids[] = (int) $shipment_id;
            }
        }

        $yandex_requests = $this->repository->findByShipmentIds($shipment_ids);

        foreach ($shipments as $id => $shipment) {
            if (!is_array($shipment) || ($shipment['carrier'] ?? '') !== 'yandex_delivery') {
                continue;
            }

            $shipment_id = $shipment['shipment_id'] ?? 0;
            if (!is_scalar($shipment_id)) {
                continue;
            }

            $yandex_request = $yandex_requests[(int) $shipment_id] ?? [];
            if (empty($yandex_request['sharing_url'])) {
                continue;
            }

            $carrier_info = isset($shipment['carrier_info']) && is_array($shipment['carrier_info'])
                ? $shipment['carrier_info']
                : [];
            $carrier_info['tracking_url'] = $yandex_request['sharing_url'];

            $shipments[$id]['carrier_info'] = $carrier_info;
        }
    }

    /**
     * Deletes local Yandex Delivery request records after related shipments are deleted.
     *
     * @param int[]|int|string $shipment_ids Deleted shipment IDs
     * @param int              $result       Number of deleted rows
     *
     * @return void
     */
    public function onDeleteShipments($shipment_ids, $result)
    {
        if (empty($result)) {
            return;
        }

        $shipment_ids = array_map('intval', is_array($shipment_ids) ? $shipment_ids : [$shipment_ids]);
        $this->repository->deleteByShipmentIds($shipment_ids);
        $this->request_service->deleteShipmentLabels($shipment_ids);
    }
}
