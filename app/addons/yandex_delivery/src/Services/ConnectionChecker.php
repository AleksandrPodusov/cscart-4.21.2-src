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

namespace Tygh\Addons\YandexDelivery\Services;

use Tygh\Addons\YandexDelivery\Shippings\YandexDeliveryApiClient;
use Tygh\Enum\NotificationSeverity;
use Exception;

/**
 * Checks that Yandex Delivery accepts the token entered in the shipping method settings.
 */
class ConnectionChecker
{
    /**
     * Asks Yandex for the account warehouses, which is the cheapest call that proves the token works.
     *
     * @param array<string, string|int|bool|null> $service_params Shipping service parameters
     *
     * @return array{severity: string, message: string}
     */
    public function check(array $service_params)
    {
        if (trim((string) ($service_params['auth_token'] ?? '')) === '') {
            return [
                'severity' => NotificationSeverity::ERROR,
                'message'  => __('yandex_delivery.auth_token_required'),
            ];
        }

        $client = new YandexDeliveryApiClient($service_params);

        try {
            $warehouses = $client->getWarehouses();
        } catch (Exception $e) {
            return [
                'severity' => NotificationSeverity::ERROR,
                'message'  => __('yandex_delivery.check.token_failed', ['[error]' => $e->getMessage()]),
            ];
        }

        $list = $warehouses['warehouses'] ?? [];

        return [
            'severity' => NotificationSeverity::NOTICE,
            'message'  => __('yandex_delivery.check.token_ok', [
                '[count]' => is_array($list) ? count($list) : 0,
            ]),
        ];
    }
}
