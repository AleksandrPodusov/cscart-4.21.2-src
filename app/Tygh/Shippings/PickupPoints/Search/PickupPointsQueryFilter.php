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

namespace Tygh\Shippings\PickupPoints\Search;

/**
 * In-memory text search filter for pickup points (search by q).
 */
class PickupPointsQueryFilter
{
    /**
     * Pickup point fields included in text search (API/DTO keys).
     *
     * @var array<int, string>
     */
    protected static $searchable_fields = [
        'pickup_point_name',
        'notes',
        'address',
        'city_name',
        'metro',
    ];

    /**
     * Filters a flat list of pickup points by search query.
     *
     * @param array  $points Pickup points as API arrays
     * @param string $q      Search query (empty string returns points unchanged)
     * @param string $match  Word matching mode: all (AND, default) or any (OR)
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public static function filterPoints(array $points, $q, $match = 'all')
    {
        $q = trim($q);
        if ($q === '') {
            return $points;
        }

        $words = self::tokenize($q);
        if ($words === []) {
            return $points;
        }

        $match = $match === 'any' ? 'any' : 'all';

        $points = self::filterPointsByWords($points, $words, $match, self::getSearchableFields());

        /**
         * Executes after filtering a flat list of pickup points by search query.
         *
         * @param array  $points Filtered pickup points
         * @param string $q      Search query
         * @param string $match  Word matching mode: all or any
         */
        fn_set_hook('pickup_points_filter_points_by_q_post', $points, $q, $match);

        return $points;
    }

    /**
     * Gets list of pickup point fields used for text search.
     *
     * @return array<int, string> Field names (extendable via pickup_points_filter_by_q_get_searchable_fields_pre hook)
     */
    protected static function getSearchableFields()
    {
        $searchable_fields = self::$searchable_fields;

        /**
         * Executes before filtering pickup points by search query.
         * Allows extending the list of searchable fields.
         *
         * @param array<int, string> $searchable_fields Searchable field names
         */
        fn_set_hook('pickup_points_filter_by_q_get_searchable_fields_pre', $searchable_fields);

        return $searchable_fields;
    }

    /**
     * @param array              $points            Pickup points as API arrays
     * @param array<int, string> $words             Search tokens
     * @param string             $match             Word matching mode: all or any
     * @param array<int, string> $searchable_fields Field names to search in
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected static function filterPointsByWords(array $points, array $words, $match, array $searchable_fields)
    {
        return array_values(array_filter(
            $points,
            static function (array $point) use ($words, $match, $searchable_fields) {
                return self::matches($point, $words, $match, $searchable_fields);
            }
        ));
    }

    /**
     * Splits search query into non-empty words after normalization.
     *
     * @param string $q Raw search query
     *
     * @return array<int, string> Search tokens (words)
     */
    protected static function tokenize($q)
    {
        $normalized_q = self::normalizeQuery($q);
        $words = preg_split('/\s+/u', $normalized_q, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? $words : [];
    }

    /**
     * Checks whether a pickup point matches all or any search words.
     *
     * @param array              $point             Pickup point as API array (DTO toArray())
     * @param array<int, string> $words             Search tokens from tokenize()
     * @param string             $match             Word matching mode: all or any
     * @param array<int, string> $searchable_fields Field names to search in
     *
     * @return bool True if the point matches the query according to $match mode
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     */
    protected static function matches(array $point, array $words, $match, array $searchable_fields)
    {
        $haystack = self::buildHaystack($point, $searchable_fields);
        if ($haystack === '') {
            return false;
        }

        if ($match === 'any') {
            foreach ($words as $word) {
                if (self::wordMatchesHaystack($word, $haystack)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($words as $word) {
            if (!self::wordMatchesHaystack($word, $haystack)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Builds a single normalized string from searchable pickup point fields.
     *
     * @param array              $point             Pickup point as API array
     * @param array<int, string> $searchable_fields Field names to concatenate
     *
     * @return string Normalized haystack for substring matching (empty if no searchable values)
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     */
    protected static function buildHaystack(array $point, array $searchable_fields)
    {
        $parts = [];
        foreach ($searchable_fields as $field) {
            if (empty($point[$field])) {
                continue;
            }

            $parts[] = self::normalizeHaystack((string) $point[$field]);
        }

        return implode('', $parts);
    }

    /**
     * Checks whether a normalized search word occurs in the haystack (case-insensitive, UTF-8).
     *
     * @param string $word     Search token
     * @param string $haystack Normalized string built from pickup point fields
     *
     * @return bool True if word is empty or found in haystack
     */
    protected static function wordMatchesHaystack($word, $haystack)
    {
        $word = self::normalizeWord($word);
        if ($word === '') {
            return true;
        }

        return mb_stripos($haystack, $word, 0, 'UTF-8') !== false;
    }

    /**
     * Normalizes query string before tokenization (parity with search_pickup_points.js).
     *
     * @param string $value Raw search query
     *
     * @return string Query with punctuation and special characters removed
     */
    protected static function normalizeQuery($value)
    {
        return preg_replace('/[&\/\\\\#,+()$~%.\'":*?<>{}]/u', '', $value) ?? '';
    }

    /**
     * Normalizes pickup point field value for haystack matching.
     *
     * @param string $value Raw field value from pickup point
     *
     * @return string Value with punctuation, whitespace and special characters removed
     */
    protected static function normalizeHaystack($value)
    {
        return preg_replace('/[&\/\\\\#,+()$~%.\'":*?<>{}\s]/u', '', $value) ?? '';
    }

    /**
     * Normalizes a single search token before matching against haystack.
     *
     * @param string $value Raw search token
     *
     * @return string Token with punctuation and special characters removed
     */
    protected static function normalizeWord($value)
    {
        return preg_replace('/[&\/\\\\#,+()$~%.\'":*?<>{}]/u', '', $value) ?? '';
    }
}
