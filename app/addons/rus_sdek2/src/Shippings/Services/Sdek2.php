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

use Tygh\Addons\RusSdek2\Shippings\PickupPoints\SdekPickupPointsByLocationLoader;
use Tygh\Enum\YesNo;
use Tygh\Http;
use Tygh\Registry;
use Tygh\Shippings\IPickupPointsService;
use Tygh\Shippings\IService;
use Tygh\Shippings\PickupPoints\Dto\LookupData\PickupPointsLookupContext;
use Tygh\Tygh;

/**
 * Sdek shipping service
 */
class Sdek2 implements IService, IPickupPointsService
{
    /**
     * Abailability multithreading in this module
     *
     * @var bool
     */
    private $allow_multithreading = false;

    /**
     * @var string Access token
     */
    private $access_token;

    /**
     * The currency in which the carrier calculates shipping costs.
     *
     * @var string $calculation_currency
     */
    public $calculation_currency = 'RUB';

    /**
     * @var array $shipping_info Shipping data
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.PropertyTypeHint
     */
    protected $shipping_info;

    /**
     * Current Company id environment
     *
     * @var int $company_id
     */
    public $company_id = 0;

    /**
     * @var string
     */
    public $city_id;

    /** @var array<array<string, float|int|string>> $goods */
    public $goods = [];

    /**
     * Updates the access token if necessary
     *
     * @return void
     */
    protected function getAccessToken()
    {
        if (!empty($this->access_token)) {
            return;
        }

        $shipping_settings = $this->shipping_info['service_params'];

        $params = [
            'grant_type' => 'client_credentials',
            'client_id' => $shipping_settings['authlogin'],
            'client_secret' => $shipping_settings['authpassword'],
        ];

        $extra = [
            'headers' => [
                'Content-type'  => 'application/x-www-form-urlencoded',
            ],
        ];

        if (YesNo::toBool($shipping_settings['test_mode'])) {
            $url = 'https://api.edu.cdek.ru/v2/oauth/token?parameters';
        } else {
            $url = 'https://api.cdek.ru/v2/oauth/token?parameters';
        }

        $response = Http::post($url, $params, $extra);
        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            return;
        }

        $this->access_token = $data['access_token'];
    }

    /**
     * Provides content of Access Request.
     *
     * @return array<array-key, string> Access Request
     */
    private function getAccessRequest(): array
    {
        $this->getAccessToken();

        return [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json',
        ];
    }

    /**
     * Returns shipping service information.
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public static function getInfo()
    {
        return [
            'name'         => __('rus_sdek2.carrier_sdek'),
            'tracking_url' => 'https://www.cdek.ru/ru/tracking?order_id=%s',
        ];
    }

    /**
     * @inheritdoc
     */
    public function isPickupPointsSupported(array $shipping_info)
    {
        $sdek_delivery = fn_get_schema('sdek', 'sdek_delivery', 'php', true);
        $tariff_code = $shipping_info['service_params']['tariff_code'] ?? null;

        return !empty($sdek_delivery[$tariff_code]['terminals'])
            && YesNo::toBool($sdek_delivery[$tariff_code]['terminals']);
    }

    /**
     * @inheritdoc
     */
    public function getPickupMinCost()
    {
        $shipping_data = $this->getStoredShippingData();
        return $shipping_data['cost'] ?? false;
    }

    /**
     * @inheritdoc
     */
    public function getPickupPointsLoader()
    {
        return new SdekPickupPointsByLocationLoader();
    }

    /**
     * @inheritdoc
     */
    public function getPickupPointsQuantity()
    {
        $shipping_data = $this->getStoredShippingData();
        return $shipping_data['pickup_points_count'] ?? false;
    }

    /**
     * Checks if shipping service allows to use multithreading
     *
     * @return bool true if allow
     */
    public function allowMultithreading()
    {
        return $this->allow_multithreading;
    }

    // phpcs:ignore
    public function prepareData($shipping_info)
    {
        $this->shipping_info = $shipping_info;
        $this->company_id = Registry::get('runtime.company_id');

        /** @var \Tygh\Addons\RusSdek2\Services\SdekService $sdek_service */
        $sdek_service = Tygh::$app['addons.rus_sdek2.sdek_service'];
        $params_product = $sdek_service->prepareProductParamsByShippingInfo($this->shipping_info);

        $this->goods = $params_product;
    }

    /**
     * Prepare request information
     *
     * @return array<string, string>
     */
    public function getRequestData()
    {
        static $request_data = null;

        $shipping_settings = $this->shipping_info['service_params'];
        $location = $this->shipping_info['package_info']['location'];

        $module = $this->shipping_info['module'];
        if (!empty($this->shipping_info['shipping_id'])) {
            $data_shipping = fn_get_shipping_info($this->shipping_info['shipping_id'], DESCR_SL);
            $module = db_get_field('SELECT module FROM ?:shipping_services WHERE service_id = ?i', $data_shipping['service_id']);
        }

        if ($module !== 'sdek2') {
            return $request_data;
        }

        /** @var \Tygh\Addons\RusSdek2\Services\CitiesService $cities_service */
        $cities_service = Tygh::$app['addons.rus_sdek2.cities_service'];
        $this->city_id = $_code = $cities_service->getCityId($location);
        $_code_sender = $shipping_settings['from_city_id'];

        if (YesNo::toBool($shipping_settings['test_mode'])) {
            $url = 'https://api.edu.cdek.ru/v2/calculator/tariff';
        } else {
            $url = 'https://api.cdek.ru/v2/calculator/tariff';
        }

        $post = [];

        if (!empty($shipping_settings['dateexecute'])) {
            $timestamp = TIME + $shipping_settings['dateexecute'] * SECONDS_IN_DAY;
            $dateexecute = date(DATE_ISO8601, $timestamp);
        } else {
            $dateexecute = date(DATE_ISO8601);
        }

        $post['date'] = $dateexecute;
        $post['from_location'] = [
            'code' => (int) $_code_sender
        ];
        $post['to_location'] = [
            'code' => (int) $_code
        ];
        $post['tariff_code'] = $shipping_settings['tariff_code'];

        $post['packages'] = $this->goods;

        $request_data = [
            'method' => 'post',
            'url' => $url,
            'data' => json_encode($post),
        ];

        return $request_data;
    }

    /**
     * Process simple request to shipping service server
     *
     * @return string Server response
     */
    public function getSimpleRates()
    {
        $response = [];
        $data = $this->getRequestData();

        if (!empty($data)) {
            $key = md5($data['data']);
            $sdek_data = fn_get_session_data($key);

            if (empty($sdek_data)) {
                $extra = [
                    'headers' => $this->getAccessRequest(),
                    'timeout' => 5,
                    'execution_timeout' => 10,
                    'log_preprocessor' => '\Tygh\Http::unescapeJsonResponse'
                ];

                $response = Http::post($data['url'], $data['data'], $extra);
                fn_set_session_data($key, $response);
            } else {
                $response = $sdek_data;
            }
        }

        return $response;
    }

    /**
     * Gets shipping cost and information about possible errors
     *
     * @param string $response Reponse from Shipping service server
     *
     * @return array Shipping cost and errors
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    public function processResponse($response)
    {
        $return = [
            'cost' => false,
            'error' => false,
        ];

        if (empty($response)) {
            $return['error'] = __('rus_sdek2.empty_response');
            return $return;
        }

        $result_array = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($result_array)) {
            $return['error'] = __('rus_sdek2.invalid_response');
            return $return;
        }

        if (!empty($result_array['requests']) && is_array($result_array['requests'])) {
            foreach ($result_array['requests'] as $req) {
                if (!empty($req['errors'])) {
                    $return['error'] = $this->processErrors($response);
                    $this->storeShippingData(['clear' => true]);
                    return $return;
                }
            }
        }

        if (!empty($result_array['errors']) || !empty($result_array['error'])) {
            $return['error'] = $this->processErrors($response);
            $this->storeShippingData(['clear' => true]);
            return $return;
        }

        $rates = $this->getSdekRates($result_array);

        if (empty($rates)) {
            $return['error'] = __('rus_sdek2.no_tariffs_available');
            return $return;
        }

        $pickup_points_list = $this->getPickupPointsLoader()->getPickupPointsByLocation(
            PickupPointsLookupContext::fromPackageInfo(
                $this->shipping_info['shipping_id'],
                $this->company_id,
                $this->shipping_info['package_info'] ?? []
            )
        );

        $this->storeShippingData($rates, $pickup_points_list);

        if (!empty($rates['price'])) {
            $return['cost'] = $rates['price'];

            if (!empty($rates['date'])) {
                $return['delivery_time'] = $rates['date'];
            }
        }

        return $return;
    }

    /**
     * Gets SDEK rates.
     *
     * @param array $response Decoded response
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    private function getSdekRates(array $response)
    {
        $rates = [];
        if (!empty($response['total_sum'])) {
            $rates['price'] = $response['total_sum'];
            if (!empty($response['period_min']) && !empty($response['period_max'])) {
                $plus = $this->shipping_info['service_params']['dateexecute'];
                $min_time = $plus + $response['period_min'];
                $max_time = $plus + $response['period_max'];
                if ($min_time === $max_time) {
                    $date = $min_time . ' ' . __('days');
                } else {
                    $date = $min_time . '-' . $max_time . ' ' . __('days');
                }
                if (!empty($date)) {
                    $rates['date'] = $date;
                }
            }
        }

        return $rates;
    }

    /**
     * Saves shipping data to session
     *
     * @param array                                               $rates              Rates data
     * @param array<\Tygh\Shippings\PickupPoints\Dto\PickupPoint> $pickup_points_list Pickup points list
     *
     * @return bool
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint
     */
    protected function storeShippingData(array $rates = [], array $pickup_points_list = [])
    {
        $shipping_info = $this->shipping_info;

        if (isset($shipping_info['keys']['group_key']) && !empty($shipping_info['keys']['shipping_id'])) {
            $group_key = $shipping_info['keys']['group_key'];
            $shipping_id = $shipping_info['keys']['shipping_id'];

            if (!empty($pickup_points_list)) {
                $selected_pickup_point_code = $shipping_info['select_pickup_point'] ?? null;
                if (!empty($selected_pickup_point_code)) {
                    foreach ($pickup_points_list as $pickup_point) {
                        if ($pickup_point->getPickupPointCode() !== $selected_pickup_point_code) {
                            continue;
                        }

                        Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['selected_pickup_point_data']
                            = $pickup_point->getExtraData();
                    }
                }
            }

            if (!empty($rates['date'])) {
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['delivery_time'] = $rates['date'];
            }
            if (!empty($rates['price'])) {
                Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['cost'] = $rates['price'];
            }

            Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['pickup_points_count'] = count($pickup_points_list);

            if (!empty($rates['clear'])) {
                unset(Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id]['selected_pickup_point_data']);
            }
        }

        return true;
    }

    /**
     * Gets error message from shipping service server
     *
     * @param string $response Reponse from Shipping service server
     *
     * @return string Text of error or false if no errors
     */
    public function processErrors($response)
    {
        $messages = [];

        $response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return __('rus_sdek2.error_calculate');
        }

        // Check errors in requests
        if (!empty($response['requests']) && is_array($response['requests'])) {
            foreach ($response['requests'] as $req) {
                if (!empty($req['errors']) && is_array($req['errors'])) {
                    foreach ($req['errors'] as $error) {
                        if (!empty($error['message'])) {
                            $messages[] = $error['message'];
                        } elseif (!empty($error['code'])) {
                            $messages[] = $error['code'];
                        }
                    }
                }
            }
        }

        // Check multiple errors
        if (!empty($response['errors']) && is_array($response['errors'])) {
            foreach ($response['errors'] as $error) {
                if (!empty($error['message'])) {
                    $messages[] = $error['message'];
                } elseif (!empty($error['code'])) {
                    $messages[] = $error['code'];
                }
            }
        }

        // Check single errors
        if (!empty($response['error'])) {
            if (is_array($response['error'])) {
                foreach ($response['error'] as $error) {
                    if (!empty($error['message'])) {
                        $messages[] = $error['message'];
                    } elseif (!empty($error['text'])) {
                        $messages[] = $error['text'];
                    }
                }
            }
        }

        if (empty($messages)) {
            return __('rus_sdek2.error_calculate');
        }

        return implode('; ', $messages);
    }

    /**
     * Fetches stored data from session
     *
     * @return array
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getStoredShippingData()
    {
        $group_key = $this->shipping_info['keys']['group_key'] ?? null;
        $shipping_id = $this->shipping_info['keys']['shipping_id'] ?? null;
        if (
            $group_key !== null && $shipping_id !== null
            && isset(Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id])
        ) {
            return Tygh::$app['session']['cart']['shippings_extra']['data'][$group_key][$shipping_id];
        }

        return [];
    }
}
