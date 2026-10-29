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

namespace Tygh\Addons\YandexDelivery\Shippings;

use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Enum\YesNo;
use Tygh\Http;
use Exception;
use stdClass;

/**
 * HTTP client for Yandex Delivery API.
 */
class YandexDeliveryApiClient
{
    const TEST_BASE_URL = 'https://b2b.taxi.tst.yandex.net';
    const PRODUCTION_BASE_URL = 'https://b2b-authproxy.taxi.yandex.net';
    const PLATFORM_PATH = '/api/b2b/platform';

    /** Closest documented size to the A6 label Yandex asks for. */
    const LABEL_SIZE_MM = '100x150';

    /**
     * Documented API error codes worth explaining in our own words.
     */
    const ERROR_CODE_MESSAGES = [
        'pickups_not_configured' => 'yandex_delivery.error.pickups_not_configured',
        'no_delivery_options'    => 'yandex_delivery.error.no_delivery_options',
        'dimensions_limit'       => 'yandex_delivery.error.dimensions_limit',
    ];

    /** Statuses Tygh\Http has no constant for. */
    const STATUS_UNAUTHORIZED = 401;
    const STATUS_TOO_MANY_REQUESTS = 429;

    /**
     * Fallback explanations by HTTP status, used when the response carries no known code.
     */
    const STATUS_MESSAGES = [
        self::STATUS_UNAUTHORIZED    => 'yandex_delivery.error.unauthorized',
        Http::STATUS_FORBIDDEN       => 'yandex_delivery.error.forbidden',
        Http::STATUS_NOT_FOUND       => 'yandex_delivery.error.not_found',
        self::STATUS_TOO_MANY_REQUESTS => 'yandex_delivery.error.too_many_requests',
    ];

    /**
     * Statuses where our own wording beats whatever text Yandex sent.
     */
    const OWN_TEXT_STATUSES = [
        self::STATUS_UNAUTHORIZED,
        Http::STATUS_FORBIDDEN,
        self::STATUS_TOO_MANY_REQUESTS,
    ];

    /** @var string */
    protected $auth_token = '';

    /** @var bool */
    protected $test_mode = false;

    /**
     * @param array<string, string|int|bool|null> $shipping_params Shipping service parameters
     *
     * @return void
     */
    public function __construct(array $shipping_params = [])
    {
        $this->auth_token = trim(ExternalValue::text($shipping_params, 'auth_token'));
        $this->test_mode = YesNo::toBool(ExternalValue::text($shipping_params, 'test_mode') ?: YesNo::NO);
    }

    /**
     * Gets an API endpoint URL.
     *
     * @param string $method_path API method path
     *
     * @return string
     */
    public function getEndpoint($method_path)
    {
        $base_url = $this->test_mode ? self::TEST_BASE_URL : self::PRODUCTION_BASE_URL;

        return $base_url . self::PLATFORM_PATH . $method_path;
    }

    /**
     * Gets request headers.
     *
     * An extra header replaces the default one of the same name instead of travelling next to it.
     *
     * @param string[] $extra_headers Extra request headers
     *
     * @return string[]
     */
    public function getHeaders(array $extra_headers = [])
    {
        $headers = [
            'Authorization: Bearer ' . $this->auth_token,
            'Content-Type: application/json',
            'Accept-Language: ' . (defined('CART_LANGUAGE') && CART_LANGUAGE === 'en' ? 'en' : 'ru'),
        ];

        foreach ($extra_headers as $extra_header) {
            $name = fn_strtolower((string) strtok($extra_header, ':')) . ':';
            $headers = array_values(array_filter($headers, static function ($header) use ($name) {
                return strpos(fn_strtolower($header), $name) !== 0;
            }));
            $headers[] = $extra_header;
        }

        return $headers;
    }

    /**
     * Sends a request and returns the body together with the status it came with.
     *
     * The two never travel apart: whoever holds a body always holds the status that judges it.
     *
     * @param string                                                 $http_method      HTTP method: get or post
     * @param string                                                 $method_path      API method path
     * @param array<string, string|int|float|bool|array|object|null> $data             Payload or query parameters
     * @param string[]                                               $extra_headers    Extra request headers
     * @param callable|string|null                                   $log_preprocessor HTTP log preprocessor
     *
     * @return array{body: string, status: int}
     *
     * @throws \Exception When auth token is empty.
     */
    protected function send(
        $http_method,
        $method_path,
        array $data = [],
        array $extra_headers = [],
        $log_preprocessor = '\Tygh\Http::unescapeJsonResponse'
    ) {
        if ($this->auth_token === '') {
            throw new Exception(__('yandex_delivery.auth_token_required'));
        }

        $extra = [
            'headers' => $this->getHeaders($extra_headers),
            'timeout' => 5,
            'execution_timeout' => 10,
        ];

        if ($log_preprocessor !== null) {
            $extra['log_preprocessor'] = $log_preprocessor;
        }

        $url = $this->getEndpoint($method_path);
        $body = $http_method === 'get'
            ? Http::get($url, $data, $extra)
            : Http::post($url, (string) json_encode($data, JSON_UNESCAPED_UNICODE), $extra);

        return ['body' => (string) $body, 'status' => Http::getStatus()];
    }

    /**
     * Sends a JSON POST request and decodes the response.
     *
     * @param string                                                 $method_path API method path
     * @param array<string, string|int|float|bool|array|object|null> $data        Request data
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When the response is empty, invalid, or contains API errors.
     */
    public function post($method_path, array $data = [])
    {
        $response = $this->send('post', $method_path, $data);

        return $this->decodeJson($response['body'], $response['status']);
    }

    /**
     * Sends a JSON GET request and decodes the response.
     *
     * @param string                                    $method_path API method path
     * @param array<string, string|int|float|bool|null> $params      Query parameters
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When the response is empty, invalid, or contains API errors.
     */
    public function get($method_path, array $params = [])
    {
        $response = $this->send('get', $method_path, $params);

        return $this->decodeJson($response['body'], $response['status']);
    }

    /**
     * Calls the pickup points list method.
     *
     * @param array<string, string|int|float|bool|array|null> $payload Request payload
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When request fails.
     */
    public function getPickupPoints(array $payload = [])
    {
        return $this->post('/pickup-points/list', $payload);
    }

    /**
     * Calls the pickup options method, which lists the dates a pickup could be booked for.
     *
     * @param string $platform_station_id Warehouse station ID
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When request fails.
     */
    public function getPickupOptions($platform_station_id)
    {
        return $this->post('/pickups/pickup-options', ['station_id' => $platform_station_id]);
    }

    /**
     * Calls the scheduled pickups list method.
     *
     * @param string $platform_station_id Warehouse station ID
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When request fails.
     */
    public function getScheduledPickups($platform_station_id)
    {
        return $this->post('/pickups/scheduled/list', [
            'filter' => ['station_id' => $platform_station_id],
        ]);
    }

    /**
     * Calls the warehouses list method.
     *
     * @param array<string, string|int|float|bool|null> $filter Warehouses filter
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When request fails.
     */
    public function getWarehouses(array $filter = [])
    {
        return $this->post('/warehouses/list', [
            'filter' => empty($filter) ? new stdClass() : $filter,
        ]);
    }

    /**
     * Creates a Yandex Delivery order request.
     *
     * @param array<string, string|int|float|bool|array|object|null> $payload Request payload
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When request fails.
     */
    public function createRequest(array $payload)
    {
        return $this->post('/request/create', $payload);
    }

    /**
     * Cancels a Yandex Delivery order request.
     *
     * Yandex only accepts this until the parcel is handed over to the courier who takes it to the recipient.
     *
     * @param string $request_id Yandex request ID
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When request fails.
     */
    public function cancelRequest($request_id)
    {
        return $this->post('/request/cancel', [
            'request_id' => $request_id,
        ]);
    }

    /**
     * Gets Yandex Delivery order request information.
     *
     * @param string $request_id Yandex request ID
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When request fails.
     */
    public function getRequestInfo($request_id)
    {
        return $this->get('/request/info', [
            'request_id' => $request_id,
            'slim' => 'true',
        ]);
    }

    /**
     * Generates labels for Yandex Delivery order requests.
     *
     * @param string[] $request_ids   Yandex request IDs
     * @param string   $generate_type Labels layout
     * @param string   $language      Labels language
     *
     * @return string PDF body
     *
     * @throws \Exception When request fails.
     */
    public function generateLabels(array $request_ids, $generate_type = 'one', $language = 'ru')
    {
        ['body' => $response, 'status' => $status] = $this->send(
            'post',
            '/request/generate-labels',
            [
                'request_ids' => array_values($request_ids),
                'generate_type' => $generate_type,
                'language' => $language,
                // Yandex requires an A6 label; the default would be A4, which no parcel printer wants.
                'label_size_mm' => self::LABEL_SIZE_MM,
            ],
            ['Accept: application/pdf'],
            [self::class, 'preprocessBinaryResponseForLog']
        );

        $data = json_decode($response, true);
        $is_json = json_last_error() === JSON_ERROR_NONE && is_array($data);

        if (!self::isSuccessStatus($status)) {
            throw new Exception(self::describeError($status, $is_json ? $data : []));
        }

        if ($response === '') {
            throw new Exception(__('yandex_delivery.empty_response'));
        }

        if (strncmp($response, '%PDF', 4) === 0) {
            return $response;
        }

        throw new Exception(__('yandex_delivery.invalid_response'));
    }

    /**
     * Prepares binary API responses for HTTP logging.
     *
     * @param string                                                      $method  HTTP method
     * @param string                                                      $url     Request URL
     * @param string|array<string, string|int|float|bool|array|null>|null $data    Request payload
     * @param array<string, string|int|float|bool|array|null>             $extra   Request options
     * @param string                                                      $content Response body
     *
     * @return array{0: string, 1: string|array<string, string|int|float|bool|array|null>|null, 2: string}
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    public static function preprocessBinaryResponseForLog($method, $url, $data, array $extra, $content)
    {
        return [
            $url,
            $data,
            '[binary response, ' . strlen($content) . ' bytes]',
        ];
    }

    /**
     * Calls the location detect method.
     *
     * @param string $location Address fragment
     *
     * @return int|null
     *
     * @throws \Exception When request fails.
     */
    public function detectGeoId($location)
    {
        $variants = $this->getLocationVariants($location);
        $variant = reset($variants);
        $geo_id = ExternalValue::int(is_array($variant) ? $variant : [], 'geo_id');

        return $geo_id > 0 ? $geo_id : null;
    }

    /**
     * Gets every location Yandex matched the string to.
     *
     * Names repeat across the country, so the caller that lets a human choose needs all of them, not the first.
     *
     * @param string $location Location string
     *
     * @return array<int, array<string, string|int|float|bool|array|object|null>>
     *
     * @throws \Exception When request fails.
     */
    public function getLocationVariants($location)
    {
        // Russian names are asked for regardless of the admin language: the answers are sifted by the settlement
        // type written in them, and in English even a city arrives as "Town of ...".
        $response = $this->send('post', '/location/detect', ['location' => $location], ['Accept-Language: ru']);
        $response = $this->decodeJson($response['body'], $response['status']);

        if (empty($response['variants']) || !is_array($response['variants'])) {
            return [];
        }

        return array_values(array_filter($response['variants'], 'is_array'));
    }



    /**
     * Decodes JSON response.
     *
     * @param string $response Raw response
     * @param int    $status   HTTP status the response came with
     *
     * @return array<string, string|int|float|bool|array|object|null>
     *
     * @throws \Exception When the response is empty, invalid, or contains API errors.
     */
    protected function decodeJson($response, $status)
    {
        $data = json_decode($response, true);
        $is_json = json_last_error() === JSON_ERROR_NONE && is_array($data);

        // The HTTP status decides whether this is an error, not the presence of a field named "message":
        // a successful response is free to carry any key without turning into a failure.
        // It also travels as the exception code, so callers can tell one failure from another.
        if (!self::isSuccessStatus($status)) {
            throw new Exception(self::describeError($status, $is_json ? $data : []), $status);
        }

        if ($response === '') {
            throw new Exception(__('yandex_delivery.empty_response'));
        }

        if (!$is_json) {
            throw new Exception(__('yandex_delivery.invalid_response'));
        }

        return $data;
    }

    /**
     * Checks whether an HTTP status means success.
     *
     * @param int $status HTTP status
     *
     * @return bool
     */
    public static function isSuccessStatus($status)
    {
        return $status >= Http::STATUS_OK && $status < 300;
    }

    /**
     * Explains a failed request: our own wording for documented codes, the Yandex text otherwise.
     *
     * @param int                                                    $status HTTP status
     * @param array<string, string|int|float|bool|array|object|null> $data   Decoded response, empty when not JSON
     *
     * @return string
     */
    public static function describeError($status, array $data)
    {
        $code = isset($data['code']) && is_scalar($data['code']) ? (string) $data['code'] : '';

        if (isset(self::ERROR_CODE_MESSAGES[$code])) {
            return __(self::ERROR_CODE_MESSAGES[$code]);
        }

        // For a rejected token, a permission problem, a rate limit or an outage the Yandex text adds nothing
        // to the status. Everywhere else it carries the specifics — "Not found station by station_id" and such.
        if (in_array($status, self::OWN_TEXT_STATUSES, true) || $status >= 500) {
            return __(self::STATUS_MESSAGES[$status] ?? 'yandex_delivery.error.server');
        }

        $message = self::extractError($data);
        if ($message !== '') {
            return $message;
        }

        if (isset(self::STATUS_MESSAGES[$status])) {
            return __(self::STATUS_MESSAGES[$status]);
        }

        return __('yandex_delivery.error.http', ['[status]' => $status ?: '—']);
    }

    /**
     * Extracts an error message from a decoded API response.
     *
     * @param array<string, string|int|float|bool|array|object|null> $data Decoded response
     *
     * @return string
     */
    public static function extractError(array $data)
    {
        $own_error = ExternalValue::text($data, '_yd_error');
        if ($own_error !== '') {
            return $own_error;
        }

        $messages = [];

        foreach (['message', 'error_description', 'error_message'] as $field) {
            if (!empty($data[$field]) && is_scalar($data[$field])) {
                $messages[] = (string) $data[$field];
            }
        }

        foreach (['error', 'errors', 'details'] as $field) {
            if (empty($data[$field])) {
                continue;
            }

            self::collectErrorMessages(is_array($data[$field]) ? $data[$field] : [$data[$field]], $messages);
        }

        $messages = array_unique(array_filter($messages));

        return empty($messages) ? '' : implode('; ', $messages);
    }

    /**
     * Collects error messages from an error node.
     *
     * @param string|int|float|bool|array|null $node     Error node
     * @param string[]                         $messages Error messages
     *
     * @return void
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected static function collectErrorMessages($node, array &$messages)
    {
        if (is_scalar($node)) {
            $messages[] = (string) $node;
            return;
        }

        if (!is_array($node)) {
            return;
        }

        foreach (['message', 'text', 'description', 'code'] as $field) {
            if (!empty($node[$field]) && is_scalar($node[$field])) {
                $messages[] = (string) $node[$field];
            }
        }

        foreach ($node as $value) {
            if (is_array($value)) {
                self::collectErrorMessages($value, $messages);
            }
        }
    }
}
