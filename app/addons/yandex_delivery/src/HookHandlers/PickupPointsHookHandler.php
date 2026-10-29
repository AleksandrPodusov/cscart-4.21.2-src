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

use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Template\Document\Variables\PickpupPointVariable;

/**
 * Fills order document variables with the selected Yandex Delivery pickup point.
 */
class PickupPointsHookHandler
{
    /**
     * Provides pickup point name, address, phone, work time, and description for documents.
     *
     * @param PickpupPointVariable $instance        Variable instance
     * @param array                $order           Order data
     * @param string               $lang_code       Two-letter language code
     * @param bool                 $is_selected     Whether a pickup point is selected
     * @param string               $name            Pickup point name
     * @param string               $phone           Pickup point phone
     * @param string               $full_address    Pickup point full address
     * @param string[]             $open_hours_raw  Raw open hours
     * @param string               $open_hours      Formatted open hours
     * @param string               $description_raw Raw description
     * @param string               $description     Formatted description
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function onPickupPointVariableInit(
        PickpupPointVariable $instance,
        array $order,
        $lang_code,
        &$is_selected,
        &$name,
        &$phone,
        &$full_address,
        array &$open_hours_raw,
        &$open_hours,
        &$description_raw,
        &$description
    ) {
        $pickup_data = $this->findPickupPointData($order);
        if ($pickup_data === null) {
            return;
        }

        $location = ExternalValue::arr($pickup_data, 'location');
        $contact = ExternalValue::arr($pickup_data, 'contact');

        $is_selected = true;
        $name = ExternalValue::text($pickup_data, 'name') ?: ExternalValue::text($location, 'address');
        $phone = ExternalValue::text($contact, 'phone');
        $full_address = ExternalValue::text($location, 'address_full') ?: ExternalValue::text($location, 'address');
        $open_hours = ExternalValue::text($pickup_data, 'work_time');
        $open_hours_raw = $open_hours === '' ? [] : [$open_hours];
        $description_raw = $description = ExternalValue::text($pickup_data, 'address_comment')
            ?: ExternalValue::text($pickup_data, 'instruction');
    }


    /**
     * Finds the pickup point this module has to report for an order.
     *
     * Shipping data is keyed by shipping group, and only one of the groups may be served by this module, so the
     * first group is not necessarily ours. The document variable holds a single pickup point, so an order that
     * ships to several pickup points can only be represented by the first one.
     *
     * @param array<string, string|int|float|array|null> $order Order data
     *
     * @return array<string, string|int|float|array|null>|null Pickup point data, or null when there is none
     */
    protected function findPickupPointData(array $order)
    {
        if (empty($order['shipping'])) {
            return null;
        }

        $shippings = is_array($order['shipping']) ? $order['shipping'] : [$order['shipping']];

        foreach ($shippings as $shipping) {
            if (!is_array($shipping) || ($shipping['module'] ?? '') !== 'yandex_delivery') {
                continue;
            }

            if (!empty($shipping['pickup_point_data']) && is_array($shipping['pickup_point_data'])) {
                return $shipping['pickup_point_data'];
            }
        }

        return null;
    }
}
