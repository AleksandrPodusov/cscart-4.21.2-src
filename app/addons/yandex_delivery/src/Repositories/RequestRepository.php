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

namespace Tygh\Addons\YandexDelivery\Repositories;

/**
 * Stores Yandex Delivery requests linked to shipments.
 */
class RequestRepository
{
    /**
     * Gets Yandex request data by shipment ID.
     *
     * @param int $shipment_id Shipment ID
     *
     * @return array<string, int|string|null>
     */
    public function findByShipmentId($shipment_id)
    {
        return db_get_row(
            'SELECT * FROM ?:yandex_delivery_requests WHERE shipment_id = ?i',
            $shipment_id
        );
    }

    /**
     * Gets Yandex request data for several shipments at once, indexed by shipment ID.
     *
     * @param int[] $shipment_ids Shipment IDs
     *
     * @return array<int, array<string, int|string|null>>
     */
    public function findByShipmentIds(array $shipment_ids)
    {
        if (empty($shipment_ids)) {
            return [];
        }

        return db_get_hash_array(
            'SELECT * FROM ?:yandex_delivery_requests WHERE shipment_id IN (?n)',
            'shipment_id',
            $shipment_ids
        );
    }

    /**
     * Gets Yandex request data for an order indexed by shipment ID.
     *
     * @param int $order_id Order ID
     *
     * @return array<int, array<string, int|string|null>>
     */
    public function findByOrderId($order_id)
    {
        return db_get_hash_array(
            'SELECT * FROM ?:yandex_delivery_requests WHERE order_id = ?i ORDER BY shipment_id',
            'shipment_id',
            $order_id
        );
    }

    /**
     * Gets created Yandex requests for an order.
     *
     * @param int $order_id Order ID
     *
     * @return array<int, array<string, int|string|null>>
     */
    public function findCreatedByOrderId($order_id)
    {
        return db_get_hash_array(
            'SELECT * FROM ?:yandex_delivery_requests'
            . ' WHERE order_id = ?i AND request_id <> ?s ORDER BY shipment_id',
            'shipment_id',
            $order_id,
            ''
        );
    }

    /**
     * Saves Yandex request data.
     *
     * @param array<string, int|string|null> $data Request data
     *
     * @return array<string, int|string|null> Saved row
     */
    public function save(array $data)
    {
        $shipment_id = (int) ($data['shipment_id'] ?? 0);
        $existing = $shipment_id ? $this->findByShipmentId($shipment_id) : [];

        $data['updated_at'] = TIME;

        if (!empty($existing)) {
            db_query(
                'UPDATE ?:yandex_delivery_requests SET ?u WHERE shipment_id = ?i',
                $data,
                $shipment_id
            );

            return $this->findByShipmentId($shipment_id);
        }

        $data['created_at'] = !empty($data['created_at']) ? (int) $data['created_at'] : TIME;

        db_query('INSERT INTO ?:yandex_delivery_requests ?e', $data);

        return $this->findByShipmentId($shipment_id);
    }

    /**
     * Deletes Yandex request rows by shipment IDs.
     *
     * @param int[] $shipment_ids Shipment IDs
     *
     * @return void
     */
    public function deleteByShipmentIds(array $shipment_ids)
    {
        $shipment_ids = array_filter(array_map('intval', $shipment_ids));
        if (empty($shipment_ids)) {
            return;
        }

        db_query('DELETE FROM ?:yandex_delivery_requests WHERE shipment_id IN (?n)', $shipment_ids);
    }
}
