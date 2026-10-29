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

use Tygh\Shippings\PickupPoints\Dto\PickupPoint;

/**
 * Keeps out of a pickup point list the points Yandex will not deliver the parcel to.
 *
 * Yandex publishes the limits per delivery network and per region, and the calculation applies the smallest of
 * those that fit the case. Checked here so the customer is not offered a point that is certain to be refused.
 *
 * @see https://yandex.ru/support/delivery-profile/ru/other-day/weight-limits
 */
class PickupPointLimits
{
    /**
     * Tells a 5Post point apart by the name Yandex sends.
     *
     * The checkout list is read without the carrier payload, where `operator_id` lives, so the name is the only
     * network marker available there. An unknown name falls back to the widest limits and keeps the point.
     */
    const NETWORK_5POST_MARKER = '5 Post';

    /**
     * Limits as weight of one box in grams, longest side and sum of all sides in centimetres, weight of the
     * whole order in grams. Yandex publishes a box limit only for ordinary pickup points; everywhere else the
     * published figure is for the order, and the two columns then hold the same number.
     */
    const LIMITS_MARKET = [30000, 150, 300, 200000];

    const LIMITS_5POST = [15000, 64, 136, 15000];

    const LIMITS_POSTAMAT = [20000, 40, 118, 20000];

    const LIMITS_RESTRICTED_REGION = [15000, 60, 180, 15000];

    /** What no delivery of any kind exceeds: the order limits published for courier delivery. */
    const LIMITS_ORDER = [200000, 300, 500, 200000];

    /**
     * Regions Yandex publishes as air-delivered or otherwise restricted, by CS-Cart state code.
     *
     * Air delivery: Amur, Irkutsk, Primorsky, Khabarovsk. Restricted: Arkhangelsk, Murmansk, Karelia, Komi,
     * Khakassia. Tatarstan carries its own note in the same table.
     */
    const RESTRICTED_STATES = ['AMU', 'IRK', 'PRI', 'KHA', 'ARK', 'MUR', 'KR', 'KO', 'KK', 'TA'];

    /**
     * Drops the points that cannot take the parcel.
     *
     * @param array<string, PickupPoint>                                      $pickup_points Points to filter
     * @param array<int, array{weight_gross: int, dx: int, dy: int, dz: int}> $places        Parcel places as sent to Yandex
     * @param string                                                          $state_code    Destination state code
     *
     * @return array{points: array<string, PickupPoint>, dropped: array<string, int>}
     */
    public function apply(array $pickup_points, array $places, $state_code)
    {
        $dropped = ['weight' => 0, 'side' => 0, 'sum' => 0];

        if ($pickup_points === [] || $places === []) {
            return ['points' => $pickup_points, 'dropped' => $dropped];
        }

        $is_restricted_region = in_array(strtoupper(trim($state_code)), self::RESTRICTED_STATES, true);

        foreach ($pickup_points as $code => $pickup_point) {
            $reason = $this->getRejectionReason($places, $this->getLimits($pickup_point, $is_restricted_region));

            if ($reason === '') {
                continue;
            }

            $dropped[$reason]++;
            unset($pickup_points[$code]);
        }

        return ['points' => $pickup_points, 'dropped' => $dropped];
    }

    /**
     * Names what about the parcel no delivery at all will take, empty string when some of them will.
     *
     * Deliberately weighed against the widest limits in reach, so the shipping method is only withdrawn when
     * every network refuses the parcel anyway — that is the same moment the filtered point list turns up empty.
     *
     * @param array<int, array{weight_gross: int, dx: int, dy: int, dz: int}> $places     Parcel places
     * @param bool                                                            $is_pickup  Delivery to a pickup point
     * @param string                                                          $state_code Destination state code
     *
     * @return string
     */
    public function getUnsupportedParcelReason(array $places, $is_pickup, $state_code)
    {
        if ($places === []) {
            return '';
        }

        $limits = $is_pickup ? self::LIMITS_MARKET : self::LIMITS_ORDER;

        if (in_array(strtoupper(trim($state_code)), self::RESTRICTED_STATES, true)) {
            $limits = $this->capBy($limits, self::LIMITS_RESTRICTED_REGION);
        }

        return $this->getRejectionReason($places, $limits);
    }

    /**
     * Gets the limits that apply to one point.
     *
     * @param PickupPoint $pickup_point         Point to weigh up
     * @param bool        $is_restricted_region Whether the destination region has its own lower limits
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function getLimits(PickupPoint $pickup_point, $is_restricted_region)
    {
        // Yandex applies the smallest of the limits that fit the case, so every one that fits is laid over the
        // previous rather than chosen instead of it: a locker of a network narrower still is bound by both.
        $limits = self::LIMITS_MARKET;

        if (mb_stripos((string) $pickup_point->getPickupPointName(), self::NETWORK_5POST_MARKER) !== false) {
            $limits = $this->capBy($limits, self::LIMITS_5POST);
        }

        if ($pickup_point->getIsPostamat()) {
            $limits = $this->capBy($limits, self::LIMITS_POSTAMAT);
        }

        if ($is_restricted_region) {
            $limits = $this->capBy($limits, self::LIMITS_RESTRICTED_REGION);
        }

        return $limits;
    }

    /**
     * Lowers the limits to another set wherever that set is stricter.
     *
     * @param array{0: int, 1: int, 2: int, 3: int} $limits Limits to cap
     * @param array{0: int, 1: int, 2: int, 3: int} $cap    Limits to cap them by
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function capBy(array $limits, array $cap)
    {
        return [
            min($limits[0], $cap[0]),
            min($limits[1], $cap[1]),
            min($limits[2], $cap[2]),
            min($limits[3], $cap[3]),
        ];
    }

    /**
     * Names what about the parcel the point will not take, empty string when it will.
     *
     * @param array<int, array{weight_gross: int, dx: int, dy: int, dz: int}> $places Parcel places
     * @param array{0: int, 1: int, 2: int, 3: int}                           $limits Limits of the point
     *
     * @return string
     */
    protected function getRejectionReason(array $places, array $limits)
    {
        $total_weight = 0;

        foreach ($places as $place) {
            if ($place['weight_gross'] > $limits[0]) {
                return 'weight';
            }

            $sides = [$place['dx'], $place['dy'], $place['dz']];

            if (max($sides) > $limits[1]) {
                return 'side';
            }

            if (array_sum($sides) > $limits[2]) {
                return 'sum';
            }

            $total_weight += $place['weight_gross'];
        }

        return $total_weight > $limits[3] ? 'weight' : '';
    }
}
