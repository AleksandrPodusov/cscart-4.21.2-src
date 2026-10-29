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

namespace Tygh\Addons\YandexDelivery\Tools;

/**
 * Filters the pickup point choice that arrives with a storefront request.
 */
class PickupPointSelection
{
    /** Station identifiers are UUIDs or 32-character hex strings; the cap only stops obvious junk. */
    const MAX_ID_LENGTH = 64;

    /** A cart has a handful of shipping groups, so anything beyond this is not a real selection. */
    const MAX_ENTRIES = 50;

    /**
     * Keeps only entries that could plausibly come from the pickup point picker.
     *
     * The session key is shared with other carriers, so the identifier format is not checked here: it is
     * enough to reject non-scalars, blanks and oversized values that would only bloat the session.
     *
     * @param string|int|float|bool|array|object|null $selection Raw value from the request
     *
     * @return array<string, array<int, string>>
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function sanitize($selection)
    {
        if (!is_array($selection)) {
            return [];
        }

        $clean = [];
        $entries = 0;

        foreach ($selection as $group_key => $points) {
            if (!is_scalar($group_key) || !is_array($points)) {
                continue;
            }

            foreach ($points as $shipping_id => $point_id) {
                if (!is_numeric($shipping_id) || !is_scalar($point_id)) {
                    continue;
                }

                $point_id = trim((string) $point_id);
                if ($point_id === '' || strlen($point_id) > self::MAX_ID_LENGTH) {
                    continue;
                }

                $clean[(string) $group_key][(int) $shipping_id] = $point_id;
                $entries++;

                if ($entries >= self::MAX_ENTRIES) {
                    return $clean;
                }
            }
        }

        return $clean;
    }
}
