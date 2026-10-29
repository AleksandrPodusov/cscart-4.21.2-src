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

namespace Tygh\Api\Entities\v40;

use Tygh;
use Tygh\Addons\StorefrontRestApi\ASraEntity;
use Tygh\Addons\StorefrontRestApi\Exceptions\ApiException;
use Tygh\Api\Response;
use Tygh\Enum\YesNo;
use Tygh\Registry;
use Tygh\Shippings\PickupPoints\Search\PickupPointsPaginator;
use Tygh\Shippings\PickupPoints\Search\PickupPointsQueryFilter;

/**
 * Class SraPickupPoints
 *
 * Provides pickup points information for the current cart/order/manual_data.
 *
 * @package Tygh\Api\Entities\v40
 *
 * phpcs:disable Squiz.Commenting.FunctionComment.TypeHintMissing
 */
class SraPickupPoints extends ASraEntity
{
    /**
     * @var array $carts Shopping carts content
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingTraversableTypeHintSpecification
     */
    protected $carts = [];

    /**
     * Returns list of pickup points for the current cart.
     *
     * GET /api/v40/sra_pickup_points
     *
     * Response data.pickup_points[]: group_key, shipping_id, pickup_points[], params (page, items_per_page, total_items, total_pages).
     * Optional filters: group_key, shipping_id, q, match, page, items_per_page, selected_shipping_only.
     *
     * selected_shipping_only (default true): loader scope — chosen shipping only, or all shippings with pickup support.
     * shipping_id: also selects the loaded shipping method when the saved cart still holds another chosen shipping.
     * page / items_per_page (default 1 / 20): pagination per group; items_per_page=0 returns the full group list without slice.
     *
     * @param int                   $id     Not used
     * @param array<string, string> $params Request parameters
     *
     * @return array<string, int|array>
     */
    public function index($id = 0, $params = [])
    {
        if ($user_relation_error = $this->getUserRelationError()) {
            return $user_relation_error;
        }

        try {
            $mode = $params['mode'] ?? null;

            if (!$mode) {
                throw new ApiException(__('api_required_params', ['[field]' => 'mode']));
            }

            switch ($mode) {
                case 'cart':
                    $cart_service_id = isset($params['cart_service_id']) && is_numeric($params['cart_service_id'])
                        ? (int) $params['cart_service_id']
                        : 0;

                    $selected_shipping_only = filter_var(
                        $params['selected_shipping_only'] ?? true,
                        FILTER_VALIDATE_BOOLEAN
                    );

                    $pickup_points = $this->getByCart($cart_service_id, $selected_shipping_only, $params);
                    break;

                case 'order':
                    if (empty($params['order_id'])) {
                        throw new ApiException(__('api_pickup_points_order_id_required_for_order_mode'));
                    }
                    $pickup_points = $this->getByOrder((int) $params['order_id']);
                    break;

                default:
                    throw new ApiException(__('api_pickup_points_invalid_mode'));
            }

            return [
                'status' => Response::STATUS_OK,
                'data'   => $this->getPickupPointsResponse($pickup_points, $params),
            ];
        } catch (ApiException $e) {
            return [
                'status' => Response::STATUS_BAD_REQUEST,
                'data' => [
                    'message' => $e->getMessage(),
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'status' => Response::STATUS_INTERNAL_SERVER_ERROR
            ];
        }
    }

    /**
     * Forbids creating pickup points via API.
     *
     * @param array<string, string> $params Request parameters
     *
     * @return array<string, int>
     */
    public function create($params)
    {
        return ['status' => Response::STATUS_FORBIDDEN];
    }

    /**
     * Forbids updating pickup points via API.
     *
     * @param int                   $id     Pickup point ID
     * @param array<string, string> $params Request parameters
     *
     * @return array<string, int>
     */
    public function update($id, $params)
    {
        return ['status' => Response::STATUS_FORBIDDEN];
    }

    /**
     * Forbids removing pickup points via API.
     *
     * @param int $id Pickup point ID
     *
     * @return array<string, int>
     */
    public function delete($id)
    {
        return ['status' => Response::STATUS_FORBIDDEN];
    }

    /**
     * @inheritDoc
     */
    public function privileges()
    {
        return [
            'index'  => true,
            'create' => false,
            'update' => false,
            'delete' => false,
        ];
    }

    /** @inheritDoc */
    public function privilegesCustomer()
    {
        return [
            'index'  => $this->auth['is_token_auth'],
            'create' => false,
            'update' => false,
            'delete' => false,
        ];
    }

    /**
     * Builds unified pickup points API response with per-group pagination.
     *
     * @param array $pickup_point_groups Pickup point groups from loader
     * @param array $params              Request parameters
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getPickupPointsResponse(array $pickup_point_groups, array $params)
    {
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $items_per_page = isset($params['items_per_page'])
            ? (int) $params['items_per_page']
            : PickupPointsPaginator::DEFAULT_ITEMS_PER_PAGE;
        $match = isset($params['match']) ? (string) $params['match'] : 'all';

        $group_key_filter = isset($params['group_key']) && is_numeric($params['group_key'])
            ? (int) $params['group_key']
            : null;
        $shipping_id_filter = isset($params['shipping_id']) && is_numeric($params['shipping_id'])
            ? (int) $params['shipping_id']
            : null;

        if (!empty($params['q'])) {
            $pickup_point_groups = $this->filterPickupPointGroupsByQuery(
                $pickup_point_groups,
                $params['q'],
                $match
            );
        }

        $groups = [];

        foreach ($pickup_point_groups as $pickup_point_group) {
            $group_key = (int) ($pickup_point_group['group_key'] ?? 0);
            $shipping_id = (int) ($pickup_point_group['shipping_id'] ?? 0);

            if ($group_key_filter !== null && $group_key !== $group_key_filter) {
                continue;
            }

            if ($shipping_id_filter !== null && $shipping_id !== $shipping_id_filter) {
                continue;
            }

            $pagination = PickupPointsPaginator::paginate(
                $pickup_point_group['pickup_points'] ?? [],
                $page,
                $items_per_page
            );

            $groups[] = [
                'group_key'     => $group_key,
                'shipping_id'   => $shipping_id,
                'pickup_points' => $pagination['items'],
                'params'        => $pagination['params'],
            ];
        }

        return ['pickup_points' => $groups];
    }

    /**
     * Filters API pickup point groups by search query.
     *
     * @param array  $pickup_point_groups Groups from loader: group_key, shipping_id, pickup_points[]
     * @param string $q                   Search query
     * @param string $match               Word matching mode: all (AND, default) or any (OR)
     *
     * @return array Filtered groups; empty pickup_points arrays are kept
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function filterPickupPointGroupsByQuery(array $pickup_point_groups, $q, $match = 'all')
    {
        $q = trim($q);
        if ($q === '') {
            return $pickup_point_groups;
        }

        $match = $match === 'any' ? 'any' : 'all';

        foreach ($pickup_point_groups as &$pickup_point_group) {
            $pickup_point_group['pickup_points'] = PickupPointsQueryFilter::filterPoints(
                $pickup_point_group['pickup_points'] ?? [],
                $q,
                $match
            );
        }
        unset($pickup_point_group);

        return $pickup_point_groups;
    }

    /**
     * Gets pickup points by cart.
     *
     * @param int                                             $cart_service_id        Cart identifier
     * @param bool                                            $selected_shipping_only Whether to fetch pickup points for selected shipping only
     * @param array<string, string|int|float|bool|array|null> $params                 Request parameters
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getByCart($cart_service_id, $selected_shipping_only = true, array $params = [])
    {
        $cart = $this->getCart($cart_service_id ?? 0);

        if (empty($cart)) {
            return [];
        }

        /** @var \Tygh\Shippings\PickupPoints\Service $pickup_points_service */
        $pickup_points_service = Tygh::$app['pickup_point.service'];

        return $pickup_points_service->getPickupPointsForApiByCart(
            $cart,
            $selected_shipping_only,
            $this->getRequestedShippingIds($cart, $params)
        ) ?? [];
    }

    /**
     * Turns the requested shipping method into the per-group form the loader takes.
     *
     * The client names one method and, when it knows it, the group it belongs to. Naming the group matters:
     * the answer is filtered by group as well, so a group nobody asked about would only be loaded to be
     * thrown away — and for a method of thousands of points that is not free.
     *
     * @param array<string, string|int|float|bool|array|null> $cart   Cart data
     * @param array<string, string|int|float|bool|array|null> $params Request parameters
     *
     * @return array<int|string, int>
     */
    protected function getRequestedShippingIds(array $cart, array $params)
    {
        if (!isset($params['shipping_id']) || !is_numeric($params['shipping_id'])) {
            return [];
        }

        $shipping_id = (int) $params['shipping_id'];

        if (isset($params['group_key']) && is_numeric($params['group_key'])) {
            return [(int) $params['group_key'] => $shipping_id];
        }

        $requested = [];

        foreach ((array) ($cart['product_groups'] ?? []) as $product_group) {
            if (is_array($product_group) && isset($product_group['shippings'][$shipping_id])) {
                $requested[(int) ($product_group['group_key'] ?? 0)] = $shipping_id;
            }
        }

        return $requested;
    }

    /**
     * Gets pickup points by cart.
     *
     * @param int $order_id Order identifier
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getByOrder($order_id)
    {
        $order_info = fn_get_order_info($order_id);

        if (empty($order_info)) {
            return [];
        }

        /** @var \Tygh\Shippings\PickupPoints\Service $pickup_points_service */
        $pickup_points_service = Tygh::$app['pickup_point.service'];

        return $pickup_points_service->getPickupPointsForApiByOrder($order_info) ?? [];
    }

    /**
     * Gets pickup points by cart.
     *
     * @param array $params Request parameters
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function getByManualData($params)
    {
        // TODO

        /* if (empty($params['shipping_id'])) {
             throw new \Exception(__('api_pickup_points_shipping_id_required'));
         }

        $pickup_points_service = Tygh::$app['pickup_point.service'];
        return $pickup_points_service->getPickupPointsByManualData($data) ?? [];*/
    }

    /**
     * Gets cart content.
     *
     * @param int $cart_service_id Cart identifier
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getCart($cart_service_id = 0)
    {
        if ($cart_service_id === 0) {
            $cart_service_ids = fn_storefront_rest_api_get_cart_service_ids($this->auth);
            $cart_service_id = reset($cart_service_ids);
        }

        if (!isset($this->carts[$cart_service_id])) {
            $cart = fn_storefront_rest_api_get_empty_cart($cart_service_id, $this->auth);
            fn_extract_cart_content($cart, $this->auth['user_id'], 'C', 'R');

            if (!empty($cart['products'])) {
                foreach ($cart['products'] as $product) {
                    if (isset($product['chosen_shipping'])) {
                        $cart['chosen_shipping'][$product['group_id']] = $product['chosen_shipping'];
                    }
                }
            }

            $this->carts[$cart_service_id] = $cart;
        }

        return $this->carts[$cart_service_id];
    }

    /**
     * Checks whether user belongs to a company.
     *
     * @param int $company_id Company identifier of a user
     *
     * @return array API response data to return if user doesn't belong to a company or an empty array if belongs
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getUserRelationError($company_id = null)
    {
        if ($company_id === null && isset($this->auth['company_id'])) {
            $company_id = $this->auth['company_id'];
        }

        if (
            fn_allowed_for('ULTIMATE')
            && $company_id
            && (int) $company_id !== $this->getCompanyId()
            && !$this->areUsersShared()
        ) {
            return [
                'status' => Response::STATUS_FORBIDDEN,
                'data'   => [
                    'message' => __('api_wrong_user_company_relation'),
                ],
            ];
        }

        return [];
    }

    /**
     * Checks whether users are shared between storefronts.
     *
     * @return bool
     */
    protected function areUsersShared()
    {
        return fn_allowed_for('ULTIMATE') && Registry::get('settings.Stores.share_users') === YesNo::YES;
    }
}
