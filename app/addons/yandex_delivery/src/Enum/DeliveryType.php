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

namespace Tygh\Addons\YandexDelivery\Enum;

/**
 * Yandex Delivery tariff values.
 */
class DeliveryType
{
    const TIME_INTERVAL = 'time_interval';
    const SELF_PICKUP = 'self_pickup';

    /**
     * Checks whether the tariff is a pickup point delivery.
     *
     * @param string|null $delivery_type Delivery type
     *
     * @return bool
     */
    public static function isPickup($delivery_type)
    {
        return $delivery_type === self::SELF_PICKUP;
    }
}
