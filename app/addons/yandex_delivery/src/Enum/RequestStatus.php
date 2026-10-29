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
 * Yandex Delivery request statuses the module has to reason about.
 */
class RequestStatus
{
    const DELIVERED = 'DELIVERY_DELIVERED';
    const CANCELLED = 'CANCELLED';
    const ERROR = 'ERROR';

    /** Statuses where the delivery is over and nothing can be called off any more. */
    const FINAL_STATUSES = [
        self::DELIVERED,
        self::CANCELLED,
        self::ERROR,
    ];

    /**
     * Checks whether the delivery has already run its course.
     *
     * @param string|null $status Request status reported by Yandex
     *
     * @return bool
     */
    public static function isFinal($status)
    {
        return in_array((string) $status, self::FINAL_STATUSES, true);
    }
}
