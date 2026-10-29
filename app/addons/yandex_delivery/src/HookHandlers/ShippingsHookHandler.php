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

use Tygh\Addons\YandexDelivery\Services\PickupDiagnostics;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\YesNo;

/**
 * Keeps the Yandex Delivery shipping method settings consistent with each other.
 */
class ShippingsHookHandler
{
    /** @var PickupDiagnostics */
    protected $pickup_diagnostics;

    /** @var ShippingDataBuilder */
    protected $shipping_data_builder;

    /**
     * @param PickupDiagnostics   $pickup_diagnostics    Explains why parcels will not be collected
     * @param ShippingDataBuilder $shipping_data_builder Owns the rule that turns a typed dimension into centimetres
     *
     * @return void
     */
    public function __construct(
        PickupDiagnostics $pickup_diagnostics,
        ShippingDataBuilder $shipping_data_builder
    ) {
        $this->pickup_diagnostics = $pickup_diagnostics;
        $this->shipping_data_builder = $shipping_data_builder;
    }

    /**
     * Drops the selected warehouses when the account or the environment behind them changes.
     *
     * A warehouse ID only exists in the account and environment it was picked in, so keeping it after a switch
     * leaves the method silently broken with "Not found station by station_id".
     *
     * @param array<string, string|int|float|array|null> $shipping_data Shipping method data about to be saved
     * @param int|string                                 $shipping_id   Shipping method ID
     * @param string                                     $lang_code     Two-letter language code
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function onUpdateShipping(array &$shipping_data, $shipping_id, $lang_code)
    {
        $shipping_id = (int) $shipping_id;
        if ($shipping_id === 0 || !isset($shipping_data['service_params'])) {
            return;
        }

        $shipping_info = fn_get_shipping_info($shipping_id, DESCR_SL);
        if (!$this->isYandexDelivery($shipping_info)) {
            return;
        }

        $submitted = $this->unserializeParams($shipping_data['service_params']);
        $saved = (array) ($shipping_info['service_params'] ?? []);

        if (!isset($submitted['auth_token'])) {
            return;
        }

        // Spaces around the token are invisible in the form, but the cache keys of pickup points, warehouses and
        // pickup diagnosis are built from the stored value: the same token pasted with a stray space fills them twice.
        $auth_token = trim(ExternalValue::text($submitted, 'auth_token'));

        if ($auth_token !== $submitted['auth_token']) {
            $submitted['auth_token'] = $auth_token;
            $shipping_data['service_params'] = serialize($submitted);
        }

        if ($this->normalizeDimensions($submitted)) {
            $shipping_data['service_params'] = serialize($submitted);
        }

        $this->warnAboutMissingDimensions($submitted);

        $credentials_changed = trim(ExternalValue::text($submitted, 'auth_token')) !== trim(ExternalValue::text($saved, 'auth_token'))
            || ExternalValue::text($submitted, 'test_mode') !== ExternalValue::text($saved, 'test_mode');

        if ($credentials_changed) {
            $had_warehouse = ($saved['source_platform_station_id'] ?? '') !== ''
                || ($saved['dropoff_platform_station_id'] ?? '') !== '';

            $submitted['source_platform_station_id'] = '';
            $submitted['dropoff_platform_station_id'] = '';
            $shipping_data['service_params'] = serialize($submitted);

            if ($had_warehouse) {
                $this->warn('yandex_delivery.warehouse_reset');

                return;
            }
        }

        $this->warnAboutMissingWarehouse($submitted);
        $this->warnAboutPickupProblems($submitted);
    }


    /**
     * Tells the administrator why Yandex will not collect parcels from the selected warehouse.
     *
     * @param array<string, string|int|float|null> $service_params Submitted service parameters
     *
     * @return void
     */
    protected function warnAboutPickupProblems(array $service_params)
    {
        $verdict = $this->pickup_diagnostics->getVerdict($service_params);

        if ($verdict !== '') {
            $this->warn($verdict);
        }
    }

    /**
     * Shows a warning about the shipping method setup.
     *
     * @param string $langvar Language variable name
     *
     * @return void
     */
    protected function warn($langvar)
    {
        fn_set_notification(NotificationSeverity::WARNING, __('warning'), __($langvar));
    }

    /**
     * Warns when no warehouse is selected for the chosen handover scheme.
     *
     * The field cannot be required in the form: it is filled from Yandex, and an account with no warehouse yet
     * would leave the settings page unsavable, with no way to correct even the token.
     *
     * @param array<string, string|int|float|null> $service_params Submitted service parameters
     *
     * @return void
     */
    protected function warnAboutMissingWarehouse(array $service_params)
    {
        $field = YesNo::toBool((string) ($service_params['use_dropoff'] ?? YesNo::YES))
            ? 'dropoff_platform_station_id'
            : 'source_platform_station_id';

        if (trim((string) ($service_params[$field] ?? '')) !== '') {
            return;
        }

        $this->warn('yandex_delivery.' . $field . '_required');
    }

    /**
     * Stores the default parcel dimensions as whole numbers, the way the core stores a product box.
     *
     * The field takes any text, and what the payload could not read used to reach Yandex as one centimetre.
     *
     * @param array<string, string|int|float|null> $service_params Submitted parameters, normalized in place
     *
     * @return bool Whether any value was changed
     *
     * @see \fn_update_product()
     */
    protected function normalizeDimensions(array &$service_params)
    {
        $changed = false;

        foreach (['length', 'width', 'height'] as $dimension) {
            if (!isset($service_params[$dimension])) {
                continue;
            }

            $normalized = $this->shipping_data_builder->normalizeDimension($service_params[$dimension]);

            if ((string) $service_params[$dimension] === (string) $normalized) {
                continue;
            }

            $service_params[$dimension] = $normalized;
            $changed = true;
        }

        return $changed;
    }

    /**
     * Warns when the default parcel dimensions are left empty.
     *
     * Without them the rate calculation throws before any request, and the method silently disappears
     * from checkout for every product that has no box dimensions of its own.
     *
     * @param array<string, string|int|float|null> $service_params Submitted service parameters
     *
     * @return void
     */
    protected function warnAboutMissingDimensions(array $service_params)
    {
        foreach (['length', 'width', 'height'] as $dimension) {
            if ((float) ($service_params[$dimension] ?? 0) > 0) {
                continue;
            }

            $this->warn('yandex_delivery.dimensions_required');

            return;
        }
    }

    /**
     * Checks that the shipping method is served by this add-on.
     *
     * `fn_get_shipping_info()` does not return the module, so it is resolved from the service.
     *
     * @param array<string, string|int|float|array|null> $shipping_info Shipping method data
     *
     * @return bool
     */
    protected function isYandexDelivery(array $shipping_info)
    {
        if (empty($shipping_info['service_id'])) {
            return false;
        }

        $module = db_get_field(
            'SELECT module FROM ?:shipping_services WHERE service_id = ?i',
            $shipping_info['service_id']
        );

        return $module === 'yandex_delivery';
    }

    /**
     * Reads service parameters, which the hook receives already serialized.
     *
     * @param string|array<string, string|int|float|null> $service_params Service parameters
     *
     * @return array<string, string|int|float|null>
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function unserializeParams($service_params)
    {
        if (is_array($service_params)) {
            return $service_params;
        }

        $unserialized = @unserialize($service_params);

        return is_array($unserialized) ? $unserialized : [];
    }
}
