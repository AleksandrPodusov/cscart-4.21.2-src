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
 * In-memory pagination for pickup points API response.
 */
class PickupPointsPaginator
{
    public const DEFAULT_ITEMS_PER_PAGE = 20;

    public const MAX_ITEMS_PER_PAGE = 50;

    /**
     * Paginates a flat list of pickup points.
     *
     * @param array $pickup_points  Pickup points as API arrays
     * @param int   $page           Page number (1-based)
     * @param int   $items_per_page Items per page; 0 returns the full list (no MAX_ITEMS_PER_PAGE cap)
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public static function paginate(array $pickup_points, $page = 1, $items_per_page = self::DEFAULT_ITEMS_PER_PAGE)
    {
        $page = max(1, $page);

        if ($items_per_page === 0) {
            $page = 1;
            $items_per_page = count($pickup_points);
        } else {
            $items_per_page = max(1, min(self::MAX_ITEMS_PER_PAGE, $items_per_page));
        }

        /**
         * Executes before paginating pickup points.
         * Allows reordering or filtering the list and adjusting pagination params.
         *
         * @param array $pickup_points  Pickup points as API arrays
         * @param int   $page           Page number (1-based)
         * @param int   $items_per_page Items per page
         */
        fn_set_hook('pickup_points_paginate_pre', $pickup_points, $page, $items_per_page);

        $total_items = count($pickup_points);
        $total_pages = $total_items > 0 && $items_per_page > 0
            ? (int) ceil($total_items / $items_per_page)
            : 0;

        $offset = ($page - 1) * $items_per_page;
        $items = array_values(array_slice($pickup_points, $offset, $items_per_page));

        $params = [
            'page'           => $page,
            'items_per_page' => $items_per_page,
            'total_items'    => $total_items,
            'total_pages'    => $total_pages,
        ];

        /**
         * Executes after paginating pickup points.
         * Allows modifying the page slice or pagination metadata.
         *
         * @param array $pickup_points  Pickup points as API arrays
         * @param int   $page           Page number (1-based)
         * @param int   $items_per_page Items per page
         * @param int   $total_items    Total pickup points count
         * @param int   $total_pages    Total pages count
         * @param array $items          Pickup points on the current page
         * @param array $params         Pagination metadata
         */
        fn_set_hook(
            'pickup_points_paginate_post',
            $pickup_points,
            $page,
            $items_per_page,
            $total_items,
            $total_pages,
            $items,
            $params
        );

        return [
            'items'  => $items,
            'params' => $params,
        ];
    }
}
