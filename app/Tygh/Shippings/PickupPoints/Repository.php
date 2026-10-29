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

namespace Tygh\Shippings\PickupPoints;

use Tygh;
use Tygh\Enum\YesNo;
use Tygh\Shippings\PickupPoints\Dto\PickupPoint;

/**
 * Pickup points repository
 */
class Repository
{
    /** Rows written by one statement */
    const INSERT_BATCH_SIZE = 50;

    /**
     * @param string $provider        Provider
     * @param string $cache_key       Cache key
     * @param bool   $with_extra_data Whether the carrier payload of every point is needed
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function findPickupPointsByProviderAndCacheKey($provider, $cache_key, $with_extra_data = true)
    {
        $pickup_points = [];
        // Not every list needs whole carrier payload. It stays in the table until somebody asks about a certain point.
        $pickup_point_list = db_get_array(
            'SELECT ?p FROM ?:shipping_pickup_points WHERE provider = ?s AND cache_key = ?s',
            $this->getPickupPointFields($with_extra_data),
            $provider,
            $cache_key
        );

        foreach ($pickup_point_list as $pickup_point) {
            $pickup_point_code = $pickup_point['pickup_point_code'];
            $pickup_point['is_postamat'] = YesNo::toBool($pickup_point['is_postamat']);

            $extra_data = $with_extra_data ? @unserialize($pickup_point['extra_data']) : [];
            $pickup_point['extra_data'] = is_array($extra_data) ? $extra_data : [];
            $pickup_points[$pickup_point_code] = PickupPoint::createFromArray($pickup_point);
        }

        return $pickup_points;
    }

    /**
     * Names the columns a pickup point list is read from.
     *
     * @param bool $with_extra_data Whether the carrier payload of every point is needed
     *
     * @return string
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getPickupPointFields($with_extra_data)
    {
        if ($with_extra_data) {
            return '*';
        }

        $fields = Tygh::$app['db']->getTableFields('shipping_pickup_points', ['extra_data'], true);

        return is_array($fields) && $fields !== [] ? implode(', ', $fields) : '*';
    }

    /**
     * Reads the carrier payload of a single pickup point.
     *
     * The payload does not depend on the cache key: a point carries the same data in every city list it turns up
     * in, so any row of that point answers the question.
     *
     * @param string $provider          Provider
     * @param string $pickup_point_code Pickup point code
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function getPickupPointExtraData($provider, $pickup_point_code)
    {
        $extra_data = @unserialize(db_get_field(
            'SELECT extra_data FROM ?:shipping_pickup_points WHERE provider = ?s AND pickup_point_code = ?s LIMIT 1',
            $provider,
            $pickup_point_code
        ));

        return is_array($extra_data) ? $extra_data : [];
    }

    /**
     * @param PickupPoint[] $pickup_points Array of pickup point objects
     * @param string        $provider      Shipping service
     * @param string        $cache_key     Cache key
     *
     * @return void
     */
    public function setPickupPointsByCacheKey(array $pickup_points, $provider, $cache_key)
    {
        if (empty($pickup_points)) {
            return;
        }

        $this->storePickupPoints($pickup_points, $cache_key);
        $this->setPickupPointSyncState($provider, $cache_key, time());
    }

    /**
     * Writes pickup points into the cache table without marking the city as synchronized.
     *
     * Marking it stays a separate step so that a list can be written in portions: until the mark is there,
     * `checkPickupPointsSyncStateByCacheKey()` answers "no", and a request interrupted halfway leaves rows that
     * nobody reads and that the next attempt overwrites.
     *
     * @param PickupPoint[] $pickup_points Array of pickup point objects
     * @param string        $cache_key     Cache key
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function storePickupPoints(array $pickup_points, $cache_key)
    {
        if (empty($pickup_points)) {
            return;
        }

        $current_timestamp = time();
        $rows = [];

        /** @var \Tygh\Shippings\PickupPoints\Dto\PickupPoint $pickup_point */
        foreach ($pickup_points as $pickup_point) {
            $pickup_point_array = $pickup_point->toArray();
            $pickup_point_array['extra_data'] = serialize($pickup_point_array['extra_data']);
            $pickup_point_array['is_postamat'] = $pickup_point_array['is_postamat'] ? YesNo::YES : YesNo::NO;
            $pickup_point_array['cache_key'] = $cache_key;
            $pickup_point_array['created_at'] = $current_timestamp;

            $rows[] = $pickup_point_array;
        }

        foreach (array_chunk($rows, self::INSERT_BATCH_SIZE) as $chunk) {
            db_replace_into('shipping_pickup_points', $chunk, true);
        }
    }

    /**
     * Clear pickup points from cache tables that are olders than 24 hours.
     *
     * @return void
     */
    public function clearOldPickupPoints()
    {
        $current_time = time();
        $compare_time = $current_time - SECONDS_IN_DAY;

        db_query('DELETE FROM ?:shipping_pickup_points_sync_state WHERE last_synced_at < ?i', $compare_time);
        db_query('DELETE FROM ?:shipping_pickup_points WHERE created_at < ?i', $compare_time);
    }

    /**
     * @param string $provider  Shipping service
     * @param string $cache_key Cache key
     *
     * @return bool
     */
    public function checkPickupPointsSyncStateByCacheKey($provider, $cache_key)
    {
        $this->clearOldPickupPoints();

        $fresh_states = db_get_fields(
            'SELECT * FROM ?:shipping_pickup_points_sync_state WHERE last_synced_at > (UNIX_TIMESTAMP() - ?i)'
            . ' AND provider = ?s AND synced_cache_key = ?s',
            SECONDS_IN_DAY,
            $provider,
            $cache_key
        );

        return !empty($fresh_states);
    }


    /**
     * @param string $provider  Shipping service
     * @param string $cache_key Cache key
     * @param int    $timestamp Current timestamp (passed throw argument to sync all timestamps for one request)
     *
     * @return void
     */
    public function setPickupPointSyncState($provider, $cache_key, $timestamp)
    {
        $pickup_point_sync_state_data = [
            'provider' => $provider,
            'synced_cache_key' => $cache_key,
            'last_synced_at' => $timestamp,
        ];

        db_replace_into('shipping_pickup_points_sync_state', $pickup_point_sync_state_data);
    }

    /**
     * @param int $shipping_id Shipping identifier
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function getShippingInfoForPickupPoints($shipping_id)
    {
        return db_get_row(
            'SELECT '
            . '?:shippings.shipping_id, '
            . '?:shippings.company_id, '
            . '?:shipping_descriptions.shipping, '
            . '?:shipping_descriptions.delivery_time, '
            . '?:shippings.rate_calculation, '
            . '?:shippings.service_params, '
            . '?:shippings.destination, '
            . '?:shippings.min_weight, '
            . '?:shippings.max_weight, '
            . '?:shippings.service_id, '
            . '?:shipping_services.module, '
            . '?:shipping_services.code as service_code, '
            . '?:shippings.is_address_required '
            . 'FROM ?:shippings '
            . 'LEFT JOIN ?:shipping_descriptions ON ?:shippings.shipping_id = ?:shipping_descriptions.shipping_id '
            . 'LEFT JOIN ?:shipping_services ON ?:shipping_services.service_id = ?:shippings.service_id '
            . 'WHERE ?:shippings.shipping_id = ?i AND ?:shipping_descriptions.lang_code = ?s '
            . 'ORDER BY ?:shippings.position',
            $shipping_id,
            CART_LANGUAGE
        );
    }
}
