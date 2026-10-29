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

namespace Tygh\Shippings\Services;

use Tygh\Addons\YandexDelivery\Shippings\YandexDeliveryApiClient;
use Tygh\Addons\YandexDelivery\Enum\DeliveryType;
use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Addons\YandexDelivery\Services\ShippingDataBuilder;
use Tygh\Addons\YandexDelivery\Services\WeightConverter;
use Tygh\Addons\YandexDelivery\Shippings\PickupPoints\YandexDeliveryPickupPointsByLocationLoader;
use Tygh\Enum\NotificationSeverity;
use Tygh\Http;
use Tygh\Registry;
use Tygh\Shippings\IPickupPointsService;
use Tygh\Shippings\IService;
use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Tygh;
use Exception;

/**
 * Yandex Delivery realtime shipping service.
 */
class YandexDelivery implements IService, IPickupPointsService
{
    /** Groups the cached answers of the calculator in one place of the registry cache. */
    const RATE_CACHE_TAG = 'yandex_delivery_rates';

    /**
     * How long a priced answer may be replayed, in seconds.
     *
     * Long enough to cover one checkout and the points a customer tries on the way, short enough that a tariff
     * changed on the Yandex side shows up the same day. Overridable by `config.tweaks.yandex_delivery_rate_cache_ttl`.
     */
    const RATE_CACHE_TTL = 600;

    /**
     * How many entries the answers are spread over.
     *
     * A key of its own per parcel would never be asked for a second time, and the cache drops an entry only when
     * its key is asked for again — so nothing would ever collect them. A fixed set of entries is reused instead.
     */
    const RATE_CACHE_SLOTS = 256;

    /** How many parcels one entry keeps, and how many priced points each of them keeps. */
    const RATE_CACHE_PARCELS_PER_SLOT = 8;

    const RATE_CACHE_POINTS_PER_PARCEL = 50;

    /**
     * Yandex error codes that are about the pickup point the customer chose.
     *
     * Named rather than excluded: the list used to work the other way round, and anything Yandex answered that
     * we had not foreseen counted as the point's fault — a rejected token, an outage and a rate limit alike.
     */
    const PICKUP_POINT_ERROR_CODES = [
        'dimensions_limit',
        'no_delivery_options',
    ];

    /**
     * The currency in which the carrier calculates shipping costs.
     *
     * @var string
     */
    public $calculation_currency = ShippingDataBuilder::BILLING_CURRENCY;

    /** @var bool */
    protected $allow_multithreading = false;

    /**
     * @var array<string, string|int|float|bool|array|null>
     */
    protected $shipping_info = [];

    /**
     * @var array<string, string|int|float|bool|array|null>|null
     */
    protected $request_data = null;

    /** @var string */
    protected $request_error = '';

    /** @var bool */
    protected $request_error_logged = false;

    /** @var int */
    protected $company_id = 0;

    /** @var int HTTP status of the pricing response, kept for processResponse() */
    protected $response_status = 0;

    /** @var ShippingDataBuilder|null Built on prepareData(), which is the only moment the core hands over control */
    protected $shipping_data_builder = null;

    /**
     * Returns shipping service information.
     *
     * @return array{name: string, tracking_url: string}
     */
    public static function getInfo()
    {
        return [
            'name' => __('yandex_delivery.carrier'),
            'tracking_url' => '',
        ];
    }

    /**
     * @inheritDoc
     */
    public function isPickupPointsSupported(array $shipping_info)
    {
        return DeliveryType::isPickup(
            $this->getShippingDataBuilder()->getDeliveryType((array) ($shipping_info['service_params'] ?? []))
        );
    }

    /**
     * @inheritDoc
     */
    public function getPickupMinCost()
    {
        $shipping_data = $this->getStoredShippingData();

        return $shipping_data['cost'] ?? false;
    }

    /**
     * @inheritDoc
     */
    public function getPickupPointsQuantity()
    {
        $shipping_data = $this->getStoredShippingData();

        return $shipping_data['pickup_points_count'] ?? false;
    }

    /**
     * @inheritDoc
     */
    public function getPickupPointsLoader()
    {
        return new YandexDeliveryPickupPointsByLocationLoader(
            $this->getShippingDataBuilder(),
            $this->getPickupDiagnostics(),
            Tygh::$app['addons.yandex_delivery.pickup_point_limits']
        );
    }

    /**
     * Asks whether the parcel is beyond every delivery Yandex offers.
     *
     * @param array<string, string|int|float|bool|null> $service_params Shipping service parameters
     *
     * @return string Reason to withdraw the method, empty string when some delivery still takes the parcel
     */
    protected function getParcelOverLimitReason(array $service_params)
    {
        $package_info = (array) ($this->shipping_info['package_info'] ?? []);
        $places = [];

        // The cart hands the weight over in the store unit, so the builder converts it here — unlike the
        // pickup point lookup, which has converted it already.
        foreach ((array) ($package_info['packages'] ?? []) as $package) {
            $places[] = $this->getShippingDataBuilder()->buildPhysicalDimensions((array) $package, $service_params);
        }

        /** @var \Tygh\Addons\YandexDelivery\Services\PickupPointLimits $limits */
        $limits = Tygh::$app['addons.yandex_delivery.pickup_point_limits'];

        return $limits->getUnsupportedParcelReason(
            $places,
            DeliveryType::isPickup($this->getShippingDataBuilder()->getDeliveryType($service_params)),
            (string) (($package_info['location']['state'] ?? '') ?: '')
        );
    }

    /**
     * Gets the service that tells whether Yandex will collect parcels from the chosen warehouse.
     *
     * The core creates this shipping service with `new` and passes nothing, so the dependency is taken from
     * the container rather than handed in.
     *
     * @return \Tygh\Addons\YandexDelivery\Services\PickupDiagnostics
     */
    protected function getPickupDiagnostics()
    {
        return Tygh::$app['addons.yandex_delivery.pickup_diagnostics'];
    }

    /**
     * @inheritDoc
     */
    public function allowMultithreading()
    {
        return $this->allow_multithreading;
    }

    /**
     * Stores shipping data for the calculation that follows.
     *
     * The `IService` contract declares `@return array`, but no implementation in the product returns anything —
     * neither the core services nor the other add-on carriers. Documented here as it actually behaves.
     *
     * @param array<string, string|int|float|bool|array|null> $shipping_info Shipping data
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public function prepareData($shipping_info)
    {
        $this->shipping_info = $shipping_info;
        $this->company_id = (int) Registry::get('runtime.company_id');
        $this->shipping_data_builder = $this->createShippingDataBuilder();
    }

    /**
     * Gets the payload builder, building it when the core has not called prepareData() yet.
     *
     * @return ShippingDataBuilder
     */
    protected function getShippingDataBuilder()
    {
        if ($this->shipping_data_builder === null) {
            $this->shipping_data_builder = $this->createShippingDataBuilder();
        }

        return $this->shipping_data_builder;
    }

    /**
     * Builds the payload builder together with the store settings it needs.
     *
     * The core creates this service with `new` and passes nothing, so there is no way to have the builder handed
     * in. Reading the setting here keeps it at the boundary instead of hiding it deeper in the payload code.
     *
     * @return ShippingDataBuilder
     */
    protected function createShippingDataBuilder()
    {
        $minimum_grams = defined('YANDEX_DELIVERY_DEFAULT_WEIGHT_GRAMS')
            ? YANDEX_DELIVERY_DEFAULT_WEIGHT_GRAMS
            : WeightConverter::MINIMUM_GRAMS;

        return new ShippingDataBuilder(
            new WeightConverter(Registry::get('settings.General.weight_symbol_grams'), $minimum_grams)
        );
    }

    /**
     * @inheritDoc
     */
    public function getRequestData()
    {
        if ($this->request_data !== null) {
            return $this->request_data;
        }

        $this->request_data = [];
        $service_params = $this->shipping_info['service_params'] ?? [];

        try {
            if (trim((string) ($service_params['auth_token'] ?? '')) === '') {
                throw new Exception(__('yandex_delivery.auth_token_required'));
            }

            if (
                $this->getShippingDataBuilder()->isDropoffEnabled($service_params)
                && $this->getShippingDataBuilder()->getSourcePlatformStationId($service_params) === ''
            ) {
                $this->request_error = $this->getShippingDataBuilder()->getSourcePlatformStationRequiredMessage($service_params);

                return $this->request_data;
            }

            // Hiding the pickup points alone is not enough: a point chosen earlier stays in the cart, and the
            // method would keep quoting a price the customer has no way to complete.
            if (!$this->getPickupDiagnostics()->isPickupScheduled($service_params)) {
                $this->request_error = __('yandex_delivery.error.pickups_not_configured');

                return $this->request_data;
            }

            // Checked before the pickup point is chosen: a parcel nobody carries must not be offered at all,
            // and Yandex reports weight and dimension breaches under one code, so the reason is found here.
            $over_limit = $this->getParcelOverLimitReason($service_params);

            if ($over_limit !== '') {
                $this->request_error = __('yandex_delivery.error.parcel_over_limit');
                $this->logRequestPreparationError('Parcel exceeds Yandex limits: ' . $over_limit);

                return $this->request_data;
            }

            if ($this->isPickupDeliveryWithoutSelectedPickupPoint()) {
                $this->request_data = [
                    'method' => 'get',
                    'url' => 'yandex_delivery://pickup-point-required',
                    'data' => '',
                    'headers' => [],
                    'timeout' => 0,
                    'execution_timeout' => 0,
                    '_yd_pickup_point_required' => true,
                ];

                return $this->request_data;
            }

            $payload = $this->getShippingDataBuilder()->buildPricingCalculatorPayload($this->shipping_info);
            $client = new YandexDeliveryApiClient($service_params);

            $data = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $this->request_data = [
                'method' => 'post',
                'url' => $client->getEndpoint('/pricing-calculator'),
                'data' => $data === false ? '{}' : $data,
                'headers' => $client->getHeaders(),
                'timeout' => 5,
                'execution_timeout' => 10,
            ];
        } catch (Exception $e) {
            $this->request_error = $e->getMessage();
            $this->logRequestPreparationError($this->request_error);
        }

        return $this->request_data;
    }

    /**
     * Calculates the rate and returns the carrier response.
     *
     * The `IService` contract declares `@return array`, but every implementation returns the raw response body,
     * which the caller parses afterwards. Documented here as it actually behaves.
     *
     * @return string Raw response body
     */
    public function getSimpleRates()
    {
        $data = $this->getRequestData();

        if (empty($data)) {
            return (string) json_encode(
                ['_yd_error' => $this->request_error ?: __('yandex_delivery.error_calculate')],
                JSON_UNESCAPED_UNICODE
            );
        }

        if (!empty($data['_yd_pickup_point_required'])) {
            return '{"_yd_pickup_point_required":true,"pricing_total":0}';
        }

        $cached = $this->getCachedAnswer($data);

        if ($cached !== null) {
            $this->response_status = $cached['status'];

            return $cached['body'];
        }

        $extra = [
            'headers' => $data['headers'],
            'timeout' => $data['timeout'],
            'execution_timeout' => $data['execution_timeout'],
            'log_preprocessor' => '\Tygh\Http::unescapeJsonResponse',
        ];

        $response = Http::post($data['url'], $data['data'], $extra);
        // The status is read straight after the call and cached with the body: processResponse() runs later,
        // and a replayed cache entry must be judged by the status it actually came with.
        $this->response_status = Http::getStatus();

        // A refusal is not an answer worth replaying: cached, it would outlive the outage that caused it and
        // follow the customer until the cart changes.
        if (YandexDeliveryApiClient::isSuccessStatus($this->response_status)) {
            $this->storeAnswerInCache($data, (string) $response, $this->response_status);
        }

        return $response;
    }

    /**
     * Reads a priced answer the calculator already gave for this very request.
     *
     * @param array<string, string|int|float|bool|array|null> $data Prepared request
     *
     * @return array{body: string, status: int}|null
     */
    protected function getCachedAnswer(array $data)
    {
        if ($this->hasSession()) {
            $key = $this->getAnswerCacheKey($data);
            $body = fn_get_session_data($key);

            return empty($body)
                ? null
                : ['body' => (string) $body, 'status' => (int) fn_get_session_data($key . '_status')];
        }

        $parcel = $this->getParcelHash($data);
        $point = $this->getDestinationOfRequest($data);
        $slot = $this->getSlotOfParcel($data);

        if (!isset($slot[$parcel][$point]['at']) || $slot[$parcel][$point]['at'] + $this->getRateCacheTtl() <= TIME) {
            return null;
        }

        return ['body' => $slot[$parcel][$point]['body'], 'status' => $slot[$parcel][$point]['status']];
    }

    /**
     * Drops the pickup point the customer has just picked, leaving the method as it was before the choice.
     *
     * The state is the one the method starts checkout in — chosen, unpriced, asking for a point — so nothing
     * new has to be shown, and placing an order stays blocked while no point is taken.
     *
     * @param string $error What to write in the log; the customer is told something general instead
     *
     * @return array{cost: float|false, error: string|false} Rate for the core
     */
    protected function rollBackPickupPointChoice($error)
    {
        $this->logRequestPreparationError($error);
        $this->clearSelectedPickupPoint();

        $pickup_points = $this->getPickupPointsForCurrentShipping();

        // With no points to offer the method has nothing to fall back to, so it leaves the list as before.
        if (empty($pickup_points)) {
            $this->storeShippingData(['clear' => true]);

            return ['cost' => false, 'error' => $error];
        }

        $this->storeShippingData(['price' => 0.0, 'pickup_point_required' => true], $pickup_points);
        fn_set_notification(
            NotificationSeverity::WARNING,
            __('warning'),
            __('yandex_delivery.rate_calculation_failed')
        );

        return ['cost' => 0.0, 'error' => false];
    }

    /**
     * Keeps a priced answer for the next request that asks the very same thing.
     *
     * @param array<string, string|int|float|bool|array|null> $data     Prepared request
     * @param string                                          $response Answer body
     * @param int                                             $status   HTTP status it came with
     *
     * @return void
     */
    protected function storeAnswerInCache(array $data, $response, $status)
    {
        if ($this->hasSession()) {
            $key = $this->getAnswerCacheKey($data);
            fn_set_session_data($key, $response);
            fn_set_session_data($key . '_status', $status);

            return;
        }

        $parcel = $this->getParcelHash($data);
        $point = $this->getDestinationOfRequest($data);
        $slot = $this->dropExpiredAnswers($this->getSlotOfParcel($data), TIME - $this->getRateCacheTtl());
        $answers = isset($slot[$parcel]) ? $slot[$parcel] : [];

        // Both the point and the parcel are put back at the end, so that what is asked for stays the longest.
        unset($answers[$point], $slot[$parcel]);
        $answers[$point] = ['body' => $response, 'status' => $status, 'at' => TIME];

        // What was looked at first is dropped first: a customer compares what is in front of them, not what they left.
        $slot[$parcel] = array_slice($answers, -self::RATE_CACHE_POINTS_PER_PARCEL, null, true);

        Registry::set(
            $this->getSlotCacheKey($data),
            array_slice($slot, -self::RATE_CACHE_PARCELS_PER_SLOT, null, true)
        );
    }

    /**
     * Throws out the answers that outlived their time.
     *
     * The entry is shared and rewritten as a whole, so its own expiry says nothing about a single answer in it.
     *
     * @param array<string, array<string, array{body: string, status: int, at: int}>> $slot        Answers by parcel
     * @param int                                                                     $alive_since Oldest moment an answer may come from
     *
     * @return array<string, array<string, array{body: string, status: int, at: int}>> What is still worth keeping
     */
    protected function dropExpiredAnswers(array $slot, $alive_since)
    {
        foreach ($slot as $parcel => $answers) {
            foreach ($answers as $point => $answer) {
                if (!isset($answer['at']) || $answer['at'] <= $alive_since) {
                    unset($slot[$parcel][$point]);
                }
            }

            if (empty($slot[$parcel])) {
                unset($slot[$parcel]);
            }
        }

        return $slot;
    }

    /**
     * Tells how long a priced answer may be replayed.
     *
     * @return int Lifetime in seconds
     */
    protected function getRateCacheTtl()
    {
        $ttl = (int) Registry::ifGet('config.tweaks.yandex_delivery_rate_cache_ttl', self::RATE_CACHE_TTL);

        return $ttl > 0 ? $ttl : self::RATE_CACHE_TTL;
    }

    /**
     * Tells whether the answer has a session to live in until the next request.
     *
     * The REST API runs with `NO_SESSION`, so what is written there is gone by the time the next request of
     * the same checkout arrives — and a storefront that prices every pickup point the customer tries would ask
     * Yandex again for each of them.
     *
     * @return bool
     */
    protected function hasSession()
    {
        return !defined('NO_SESSION') || !NO_SESSION;
    }

    /**
     * Reads the entry that holds this parcel, with everything else that shares the entry with it.
     *
     * @param array<string, string|int|float|bool|array|null> $data Prepared request
     *
     * @return array<string, array<string, array{body: string, status: int, at: int}>> Answers by parcel and point
     */
    protected function getSlotOfParcel(array $data)
    {
        $key = $this->getSlotCacheKey($data);

        Registry::registerCache(
            [self::RATE_CACHE_TAG, $key],
            ['update_handlers' => [], 'ttl' => $this->getRateCacheTtl()],
            Registry::cacheLevel('static')
        );

        $slot = Registry::isExist($key) ? Registry::get($key) : [];

        return is_array($slot) ? $slot : [];
    }

    /**
     * Names the entry a parcel belongs to. Parcels share entries, and how many entries there are never changes.
     *
     * @param array<string, string|int|float|bool|array|null> $data Prepared request
     *
     * @return string
     */
    protected function getSlotCacheKey(array $data)
    {
        $slot = hexdec(substr($this->getParcelHash($data), 0, 6)) % self::RATE_CACHE_SLOTS;

        return self::RATE_CACHE_TAG . '_' . $slot;
    }

    /**
     * Tells one parcel from another: everything about the request except where it goes.
     *
     * @param array<string, string|int|float|bool|array|null> $data Prepared request
     *
     * @return string
     */
    protected function getParcelHash(array $data)
    {
        $payload = json_decode(ExternalValue::text($data, 'data'), true);
        $payload = is_array($payload) ? $payload : [];
        unset($payload['destination']);

        return md5(
            ExternalValue::text($data, 'url') . json_encode($payload) . serialize(ExternalValue::arr($data, 'headers'))
        );
    }

    /**
     * Names the pickup point the request goes to, empty string for delivery to an address.
     *
     * @param array<string, string|int|float|bool|array|null> $data Prepared request
     *
     * @return string
     */
    protected function getDestinationOfRequest(array $data)
    {
        $payload = json_decode(ExternalValue::text($data, 'data'), true);
        $payload = is_array($payload) ? $payload : [];

        return md5(json_encode($payload['destination'] ?? []));
    }

    /**
     * Names the cache entry of one whole request, the way the session has always kept it.
     *
     * @param array<string, string|int|float|bool|array|null> $data Prepared request
     *
     * @return string
     */
    protected function getAnswerCacheKey(array $data)
    {
        return md5(
            ExternalValue::text($data, 'url')
            . ExternalValue::text($data, 'data')
            . serialize(ExternalValue::arr($data, 'headers'))
        );
    }

    /**
     * @inheritDoc
     */
    public function processResponse($response)
    {
        $result = [
            'cost' => false,
            'error' => false,
        ];
        $can_retry_without_selected_pickup_point = $this->canRetryWithoutSelectedPickupPoint();

        if (empty($response)) {
            $this->storeShippingData(['clear' => true]);

            return $result;
        }

        $response_data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($response_data)) {
            // A failure with a non-JSON body still has a status to report — an HTML error page from
            // a balancer used to disappear here without a word.
            $this->logRequestPreparationError($this->describeResponseError([]));
            $this->storeShippingData(['clear' => true]);

            return $result;
        }

        if (!empty($response_data['_yd_pickup_point_required'])) {
            $pickup_points = $this->getPickupPointsForCurrentShipping();
            if (empty($pickup_points)) {
                $this->storeShippingData(['clear' => true]);

                return $result;
            }

            $this->storeShippingData(['price' => 0.0, 'pickup_point_required' => true], $pickup_points);
            $result['cost'] = 0.0;

            return $result;
        }

        $error = $this->describeResponseError($response_data);
        if ($error !== '') {
            if ($can_retry_without_selected_pickup_point && $this->isPickupPointRelatedError($response_data)) {
                return $this->retryWithoutSelectedPickupPoint();
            }

            // A rate limit says nothing about the point, so the choice is simply not taken: the method goes back
            // to the state it was in before the tap, and the customer is asked to try again.
            if ($this->response_status === YandexDeliveryApiClient::STATUS_TOO_MANY_REQUESTS) {
                return $this->rollBackPickupPointChoice($error);
            }

            $result['error'] = $error;
            $this->storeShippingData(['clear' => true]);

            return $result;
        }

        $price = $this->extractPrice($response_data['pricing_total'] ?? null);
        if ($price === null) {
            // A success without a price is an anomaly, not a verdict on the chosen point: dropping the customer
            // choice here used to turn any hiccup on the Yandex side into "your pickup point is gone".
            $this->logRequestPreparationError(__('yandex_delivery.error_calculate'));
            $this->storeShippingData(['clear' => true]);

            return $result;
        }

        $rates = [
            'price' => $price,
        ];

        if (!empty($response_data['delivery_days'])) {
            $rates['date'] = (int) $response_data['delivery_days'] . ' ' . __('days');
            $result['delivery_time'] = $rates['date'];
        }

        $pickup_points = $this->getPickupPointsForCurrentShipping();
        $this->storeShippingData($rates, $pickup_points);

        // A priced delivery is the one fact that disproves an earlier refusal, so only it clears the record.
        // The "choose a pickup point" state also carries a price of zero and must not be mistaken for it.
        $this->forgetRefusedPickupPoint();

        $result['cost'] = $price;

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function processErrors($response)
    {
        $response_data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($response_data)) {
            $response_data = [];
        }

        return $this->describeResponseError($response_data);
    }

    /**
     * Explains a pricing response, judging success by the HTTP status rather than by field names.
     *
     * A zero status means no request was made — the response is one this service built itself, and the only
     * thing worth reporting is the message it carries.
     *
     * @param array<string, string|int|float|bool|array|null> $response_data Decoded pricing response
     *
     * @return string
     */
    protected function describeResponseError(array $response_data)
    {
        if ($this->response_status === 0) {
            return isset($response_data['_yd_error']) && is_scalar($response_data['_yd_error'])
                ? (string) $response_data['_yd_error']
                : '';
        }

        if (YandexDeliveryApiClient::isSuccessStatus($this->response_status)) {
            return '';
        }

        return YandexDeliveryApiClient::describeError($this->response_status, $response_data);
    }

    /**
     * Gets pickup points for the current shipping calculation.
     *
     * @return array<string, \Tygh\Shippings\PickupPoints\Dto\PickupPoint>
     */
    protected function getPickupPointsForCurrentShipping()
    {
        $service_params = $this->shipping_info['service_params'] ?? [];

        if (!DeliveryType::isPickup($this->getShippingDataBuilder()->getDeliveryType($service_params))) {
            return [];
        }

        if (empty($this->shipping_info['shipping_id'])) {
            return [];
        }

        $lookup_context = PickupPointsLookupContext::fromPackageInfo(
            ExternalValue::int($this->shipping_info, 'shipping_id'),
            $this->company_id,
            ExternalValue::arr($this->shipping_info, 'package_info')
        );
        // Only the point the customer picked is read in full, and that one is fetched by code.
        $lookup_context->setExtraDataRequired(false);

        return $this->getPickupPointsLoader()->getPickupPointsByLocation($lookup_context);
    }

    /**
     * Retries pickup shipping calculation without selected pickup point.
     *
     * @return array{cost: float|false, error: bool, delivery_time?: string}
     */
    protected function retryWithoutSelectedPickupPoint()
    {
        $selected_pickup_point = ExternalValue::text($this->shipping_info, 'select_pickup_point');
        unset($this->shipping_info['select_pickup_point']);
        $this->request_data = null;

        $fallback_result = $this->processResponse($this->getSimpleRates());
        if (($fallback_result['cost'] ?? false) === false) {
            $this->shipping_info['select_pickup_point'] = $selected_pickup_point;
            $this->request_data = null;

            return $fallback_result;
        }

        $this->clearSelectedPickupPoint();
        $this->rememberRefusedPickupPoint($selected_pickup_point);
        $group_key = $this->shipping_info['keys']['group_key'] ?? '';
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? '';

        fn_set_notification(
            NotificationSeverity::WARNING,
            __('warning'),
            __('yandex_delivery.selected_pickup_point_unavailable'),
            '',
            'yandex_delivery_selected_pickup_point_unavailable_' . $group_key . '_' . $shipping_id
        );

        return $fallback_result;
    }

    /**
     * Stores shipping calculation data in session.
     *
     * @param array{clear?: bool, date?: string, price?: float, pickup_point_required?: bool} $rates              Calculated rates
     * @param array<string, \Tygh\Shippings\PickupPoints\Dto\PickupPoint>                     $pickup_points_list Pickup points
     *
     * @return bool
     */
    protected function storeShippingData(array $rates = [], array $pickup_points_list = [])
    {
        $group_key = $this->shipping_info['keys']['group_key'] ?? null;
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? null;

        if ($group_key === null || $shipping_id === null) {
            return false;
        }

        if (!isset(Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id])) {
            Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id] = [];
        }

        $storage = &Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id];

        if (!empty($rates['clear'])) {
            unset(
                $storage['selected_pickup_point_data'],
                $storage['pickup_point_required'],
                $storage['refused_pickup_point'],
                $storage['cost'],
                $storage['delivery_time']
            );
            $storage['pickup_points_count'] = 0;

            return true;
        }

        if (!empty($rates['date'])) {
            $storage['delivery_time'] = $rates['date'];
        } else {
            unset($storage['delivery_time']);
        }

        if (isset($rates['price'])) {
            $storage['cost'] = $rates['price'];
        }

        $storage['pickup_points_count'] = count($pickup_points_list);
        $storage['pickup_point_required'] = !empty($rates['pickup_point_required']);

        unset($storage['selected_pickup_point_data']);

        $selected_pickup_point_code = trim(ExternalValue::text($this->shipping_info, 'select_pickup_point'));
        if ($selected_pickup_point_code === '') {
            return true;
        }

        foreach ($pickup_points_list as $pickup_point) {
            if ($pickup_point->getPickupPointCode() !== $selected_pickup_point_code) {
                continue;
            }

            $extra_data = $pickup_point->getExtraData();

            if ($extra_data === []) {
                /** @var \Tygh\Shippings\PickupPoints\Repository $pickup_points_repository */
                $pickup_points_repository = Tygh::$app['pickup_point.repository'];
                $extra_data = $pickup_points_repository->getPickupPointExtraData(
                    $pickup_point->getProvider(),
                    $selected_pickup_point_code
                );
            }

            $storage['selected_pickup_point_data'] = $extra_data;
            break;
        }

        return true;
    }

    /**
     * Checks whether current failed calculation can be retried without a selected pickup point.
     *
     * @return bool
     */
    protected function canRetryWithoutSelectedPickupPoint()
    {
        if (trim(ExternalValue::text($this->shipping_info, 'select_pickup_point')) === '') {
            return false;
        }

        $service_params = $this->shipping_info['service_params'] ?? [];
        if (!DeliveryType::isPickup($this->getShippingDataBuilder()->getDeliveryType($service_params))) {
            return false;
        }

        return true;
    }

    /**
     * Checks whether a failed calculation is caused by the selected pickup point rather than by anything else.
     *
     * Only an answer naming one of the known pickup point problems counts. Everything else — a setup problem,
     * an outage, a rate limit, a code nobody has seen yet — leaves the customer choice alone and is reported
     * as it is, so the real cause reaches the administrator instead of being dressed up as a missing point.
     *
     * @param array<string, string|int|float|bool|array|object|null> $response_data Decoded API response
     *
     * @return bool
     */
    protected function isPickupPointRelatedError(array $response_data)
    {
        $code = isset($response_data['code']) && is_scalar($response_data['code'])
            ? strtolower(trim((string) $response_data['code']))
            : '';

        return in_array($code, self::PICKUP_POINT_ERROR_CODES, true);
    }

    /**
     * Checks whether pickup delivery is waiting for a pickup point selection.
     *
     * @return bool
     */
    protected function isPickupDeliveryWithoutSelectedPickupPoint()
    {
        $service_params = $this->shipping_info['service_params'] ?? [];

        return DeliveryType::isPickup($this->getShippingDataBuilder()->getDeliveryType($service_params))
            && trim(ExternalValue::text($this->shipping_info, 'select_pickup_point')) === '';
    }

    /**
     * Remembers the pickup point Yandex refused to deliver to.
     *
     * The choice is dropped from the cart on refusal, but the storefront keeps posting it with the order form,
     * so the refusal has to outlive the calculation that learned about it.
     *
     * @param string $pickup_point_code Pickup point the calculation was refused for
     *
     * @return void
     */
    protected function rememberRefusedPickupPoint($pickup_point_code)
    {
        $group_key = $this->shipping_info['keys']['group_key'] ?? null;
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? null;
        $pickup_point_code = trim($pickup_point_code);

        if ($group_key === null || $shipping_id === null || $pickup_point_code === '') {
            return;
        }

        Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['refused_pickup_point']
            = $pickup_point_code;
    }

    /**
     * Forgets the refusal, so a pickup point that works again may be chosen again.
     *
     * @return void
     */
    protected function forgetRefusedPickupPoint()
    {
        $group_key = $this->shipping_info['keys']['group_key'] ?? null;
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? null;

        if ($group_key === null || $shipping_id === null) {
            return;
        }

        unset(Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['refused_pickup_point']);
    }

    /**
     * Clears selected pickup point from calculation, request, and cart session.
     *
     * @return void
     */
    protected function clearSelectedPickupPoint()
    {
        unset($this->shipping_info['select_pickup_point']);
        $this->request_data = null;

        $group_key = $this->shipping_info['keys']['group_key'] ?? null;
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? null;

        if ($group_key === null || $shipping_id === null) {
            return;
        }

        if (
            empty(Tygh::$app['session']['cart']['select_pickup_point'][$group_key])
            || !is_array(Tygh::$app['session']['cart']['select_pickup_point'][$group_key])
        ) {
            return;
        }

        // Only this shipping's entry is dropped. An emptied group and the key itself are left in place:
        // the core and other carriers fall back to the request when the key is gone, undoing the reset.
        unset(Tygh::$app['session']['cart']['select_pickup_point'][$group_key][$shipping_id]);
    }

    /**
     * Gets previously stored shipping data.
     *
     * @return array{
     *     cost?: float,
     *     delivery_time?: string,
     *     pickup_points_count?: int,
     *     pickup_point_required?: bool,
     *     selected_pickup_point_data?: array<string, string|int|float|bool|array|null>
     * }
     */
    protected function getStoredShippingData()
    {
        $group_key = $this->shipping_info['keys']['group_key'] ?? null;
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? null;

        if (
            $group_key !== null
            && $shipping_id !== null
            && isset(Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id])
        ) {
            return Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id];
        }

        return [];
    }

    /**
     * Extracts price in rubles from the API response.
     *
     * @param float|int|string|bool|array<string|int>|object|null $price Price value
     *
     * @return float|null
     */
    protected function extractPrice($price)
    {
        if ($price === null) {
            return null;
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        if (!is_scalar($price)) {
            return null;
        }

        if (preg_match('/(-?\d+(?:[\.,]\d+)?)/', (string) $price, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return null;
    }


    /**
     * Logs an error that happened before the API request was sent.
     *
     * @param string $message Error message
     *
     * @return void
     */
    protected function logRequestPreparationError($message)
    {
        if ($this->request_error_logged || $message === '') {
            return;
        }

        $service_params = $this->shipping_info['service_params'] ?? [];
        $client = new YandexDeliveryApiClient($service_params);

        fn_log_event(
            'requests',
            'shipping',
            [
                'url'      => $client->getEndpoint('/pricing-calculator'),
                'response' => $message,
                'data'     => var_export(['error' => $message], true),
                'shipping' => 'yandex_delivery',
            ]
        );

        $this->request_error_logged = true;
    }
}
