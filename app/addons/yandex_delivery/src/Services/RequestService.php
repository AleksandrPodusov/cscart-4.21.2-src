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

use Tygh\Addons\YandexDelivery\Tools\ExternalValue;
use Tygh\Addons\YandexDelivery\Shippings\YandexDeliveryApiClient;
use Tygh\Addons\YandexDelivery\Repositories\RequestRepository;
use Tygh\Addons\YandexDelivery\Enum\RequestStatus;
use Tygh\Enum\YesNo;
use Tygh\Http;
use Exception;

/**
 * Manages Yandex Delivery requests created from shipments.
 */
class RequestService
{
    /** @var RequestRepository */
    protected $repository;

    /** @var RequestDataBuilder */
    protected $request_data_builder;

    /**
     * @param RequestRepository  $repository           Request repository
     * @param RequestDataBuilder $request_data_builder Request data builder
     *
     * @return void
     */
    public function __construct(
        RequestRepository $repository,
        RequestDataBuilder $request_data_builder
    ) {
        $this->repository = $repository;
        $this->request_data_builder = $request_data_builder;
    }

    /**
     * Creates a Yandex Delivery request for a shipment.
     *
     * @param int $shipment_id Shipment ID
     *
     * @return array<string, int|string|null> Yandex request row
     *
     * @throws \Exception When request cannot be created.
     */
    public function createRequestByShipmentId($shipment_id)
    {
        $shipment = $this->getShipment($shipment_id);
        $shipping_info = $this->getShippingInfo($shipment);
        $this->assertYandexShipment($shipment, $shipping_info);

        $existing = $this->repository->findByShipmentId($shipment_id);
        if (!empty($existing['request_id'])) {
            return $existing;
        }

        $order_info = $this->getOrderInfo((int) $shipment['order_id']);
        // The identifier is kept across retries on purpose: a request that reached Yandex while its answer was
        // lost comes back on the repeat as `208` with the same request_id instead of turning into a second parcel.
        $payload = $this->request_data_builder->build($order_info, $shipment, $shipping_info);
        $operator_request_id = $this->request_data_builder->getOperatorRequestId($order_info, $shipment);
        $client = $this->createClient($shipping_info);

        try {
            $response = $client->createRequest($payload);
            $request_id = ExternalValue::text($response, 'request_id');

            if ($request_id === '') {
                throw new Exception(__('yandex_delivery.request_id_not_found'));
            }

            db_query(
                'UPDATE ?:shipments SET tracking_number = ?s, carrier = ?s WHERE shipment_id = ?i',
                $request_id,
                'yandex_delivery',
                $shipment_id
            );

            return $this->repository->save([
                'shipment_id' => (int) $shipment['shipment_id'],
                'order_id' => (int) $shipment['order_id'],
                'shipping_id' => (int) $shipment['shipping_id'],
                'request_id' => $request_id,
                'operator_request_id' => $operator_request_id,
                'status' => 'CREATED',
                'status_description' => '',
                'sharing_url' => $this->fetchSharingUrl($client, $request_id),
                'last_error' => '',
            ]);
        } catch (Exception $e) {
            $this->repository->save([
                'shipment_id' => (int) $shipment['shipment_id'],
                'order_id' => (int) $shipment['order_id'],
                'shipping_id' => (int) $shipment['shipping_id'],
                'operator_request_id' => $operator_request_id,
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancels the Yandex request behind a shipment.
     *
     * Yandex only calls a delivery off before it is handed to the courier who brings it to the recipient, so a
     * finished delivery is left alone: there is nothing to stop, and refusing to delete it would trap the manager.
     *
     * @param int $shipment_id Shipment ID
     *
     * @return bool Whether a request was cancelled
     *
     * @throws \Exception When Yandex refuses to cancel a delivery that is still under way.
     */
    public function cancelByShipmentId($shipment_id)
    {
        $yandex_request = $this->repository->findByShipmentId($shipment_id);
        if (empty($yandex_request['request_id'])) {
            return false;
        }

        // The row exists only for shipments this module created, so the carrier needs no further checking.
        $shipment = $this->getShipment($shipment_id);
        $request_id = ExternalValue::text($yandex_request, 'request_id');
        $client = $this->createClient($this->getShippingInfo($shipment));

        try {
            // The stored status is only refreshed when someone asks for it, so it cannot be trusted here.
            $info = $client->getRequestInfo($request_id);
            if (RequestStatus::isFinal($this->extractStatus($info))) {
                return false;
            }

            $response = $client->cancelRequest($request_id);
        } catch (Exception $e) {
            // Yandex does not know this request, so there is no delivery to stop and nothing to keep the user from.
            if ($e->getCode() === Http::STATUS_NOT_FOUND) {
                return false;
            }

            throw $e;
        }

        // A refusal arrives as a normal response with an error status, not as a failed HTTP request.
        if ($this->extractStatus($response) === RequestStatus::ERROR) {
            throw new Exception(
                ExternalValue::text($response, 'description')
                    ?: ExternalValue::text($response, 'reason')
                    ?: __('yandex_delivery.cancel_refused')
            );
        }

        return true;
    }

    /**
     * Asks Yandex for the recipient tracking page of a freshly created request.
     *
     * The link only comes with `request/info`, and status refresh is manual, so without this the customer would
     * have no tracking until someone opened the order and pressed the button.
     *
     * @param \Tygh\Addons\YandexDelivery\Shippings\YandexDeliveryApiClient $client     Configured API client
     * @param string                                                        $request_id Yandex request ID
     *
     * @return string Tracking page URL, empty when Yandex did not report one
     */
    protected function fetchSharingUrl(YandexDeliveryApiClient $client, $request_id)
    {
        try {
            return $this->extractSharingUrl($client->getRequestInfo($request_id));
        } catch (Exception $e) {
            // The request itself is already created; a missing link is not worth failing the whole operation.
            return '';
        }
    }

    /**
     * Reads the recipient tracking page URL out of a Yandex response.
     *
     * @param array<string, string|int|float|bool|array|object|null> $response Yandex response
     *
     * @return string
     */
    protected function extractSharingUrl(array $response)
    {
        return isset($response['sharing_url']) && is_scalar($response['sharing_url'])
            ? (string) $response['sharing_url']
            : '';
    }

    /**
     * Reads the status out of a Yandex response, wherever that response happens to carry it.
     *
     * @param array<string, string|int|float|bool|array|object|null> $response Yandex response
     *
     * @return string
     */
    protected function extractStatus(array $response)
    {
        if (isset($response['state']['status']) && is_scalar($response['state']['status'])) {
            return (string) $response['state']['status'];
        }

        return isset($response['status']) && is_scalar($response['status']) ? (string) $response['status'] : '';
    }


    /**
     * Updates local status from Yandex Delivery.
     *
     * @param int $shipment_id Shipment ID
     *
     * @return array<string, int|string|null> Yandex request row
     *
     * @throws \Exception When status cannot be updated.
     */
    public function updateStatusByShipmentId($shipment_id)
    {
        $yandex_request = $this->repository->findByShipmentId($shipment_id);
        if (empty($yandex_request['request_id'])) {
            throw new Exception(__('yandex_delivery.no_request_id'));
        }

        $shipment = $this->getShipment($shipment_id);
        $shipping_info = $this->getShippingInfo($shipment);
        $this->assertYandexShipment($shipment, $shipping_info);

        $client = $this->createClient($shipping_info);

        try {
            $response = $client->getRequestInfo(ExternalValue::text($yandex_request, 'request_id'));
            $state = $response['state'] ?? [];

            return $this->repository->save([
                'shipment_id' => (int) $shipment['shipment_id'],
                'order_id' => (int) $shipment['order_id'],
                'shipping_id' => (int) $shipment['shipping_id'],
                'request_id' => $yandex_request['request_id'],
                'operator_request_id' => $yandex_request['operator_request_id'],
                'status' => (string) ($state['status'] ?? ''),
                'status_description' => (string) ($state['description'] ?? ''),
                'sharing_url' => $this->extractSharingUrl($response),
                'last_error' => '',
            ]);
        } catch (Exception $e) {
            $this->repository->save([
                'shipment_id' => (int) $shipment['shipment_id'],
                'order_id' => (int) $shipment['order_id'],
                'shipping_id' => (int) $shipment['shipping_id'],
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Gets a local PDF label path for a shipment.
     *
     * @param int $shipment_id Shipment ID
     *
     * @return string Absolute file path
     *
     * @throws \Exception When label cannot be generated.
     */
    public function getShipmentLabelFilePath($shipment_id)
    {
        $yandex_request = $this->repository->findByShipmentId($shipment_id);
        if (empty($yandex_request['request_id'])) {
            throw new Exception(__('yandex_delivery.no_request_id'));
        }

        $request_id = ExternalValue::text($yandex_request, 'request_id');
        $file_path = $this->getShipmentLabelDir($shipment_id) . 'label_' . md5($request_id) . '.pdf';
        if (is_file($file_path)) {
            return $file_path;
        }

        $pdf = $this->generateLabels(
            [$request_id],
            ExternalValue::int($yandex_request, 'shipping_id')
        );
        $this->savePdf($file_path, $pdf);

        return $file_path;
    }

    /**
     * Gets a local PDF labels path for all created Yandex requests in an order.
     *
     * @param int $order_id Order ID
     *
     * @return string Absolute file path
     *
     * @throws \Exception When labels cannot be generated.
     */
    public function getOrderLabelsFilePath($order_id)
    {
        $yandex_requests = $this->repository->findCreatedByOrderId($order_id);
        if (empty($yandex_requests)) {
            throw new Exception(__('yandex_delivery.no_request_id'));
        }

        $request_ids = [];
        $shipping_id = 0;
        foreach ($yandex_requests as $yandex_request) {
            $request_ids[] = ExternalValue::text($yandex_request, 'request_id');
            if (!$shipping_id) {
                $shipping_id = ExternalValue::int($yandex_request, 'shipping_id');
            }
        }

        $file_path = $this->getOrderLabelsPath($order_id, $request_ids);
        if (is_file($file_path)) {
            return $file_path;
        }

        $pdf = $this->generateLabels($request_ids, $shipping_id);
        $this->savePdf($file_path, $pdf);

        return $file_path;
    }

    /**
     * Gets shipment data.
     *
     * @param int $shipment_id Shipment ID
     *
     * @return array
     *
     * @throws \Exception When shipment is missing.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getShipment($shipment_id)
    {
        [$shipments,] = fn_get_shipments_info([
            'advanced_info' => true,
            'shipment_id' => $shipment_id,
        ]);

        $shipment = reset($shipments);
        if (empty($shipment)) {
            throw new Exception(__('yandex_delivery.shipment_not_found'));
        }

        return $shipment;
    }

    /**
     * Gets order data.
     *
     * @param int $order_id Order ID
     *
     * @return array
     *
     * @throws \Exception When order is missing.
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingTraversableTypeHintSpecification
     */
    protected function getOrderInfo($order_id)
    {
        $order_info = fn_get_order_info($order_id, false, true, true, false, DESCR_SL);
        if (empty($order_info)) {
            throw new Exception(__('yandex_delivery.order_not_found'));
        }

        return $order_info;
    }

    /**
     * Gets shipment shipping method data.
     *
     * @param array{shipping_id?: int|string} $shipment Shipment data
     *
     * @return array<string, string|int|float|bool|array<string, string|int|float|bool|null>|null>
     *
     * @throws \Exception When shipping method is missing.
     */
    protected function getShippingInfo(array $shipment)
    {
        $shipping_info = fn_get_shipping_info((int) ($shipment['shipping_id'] ?? 0), DESCR_SL);
        if (empty($shipping_info)) {
            throw new Exception(__('yandex_delivery.shipping_method_not_found'));
        }

        return $shipping_info;
    }

    /**
     * Builds an API client out of shipping method settings.
     *
     * Settings arrive as loosely typed data, so only the two fields the client needs are read, and each is read
     * as text rather than cast blindly.
     *
     * @param array<string, string|int|float|bool|array|object|null> $shipping_info Shipping method data
     *
     * @return YandexDeliveryApiClient
     */
    protected function createClient(array $shipping_info)
    {
        $service_params = ExternalValue::arr($shipping_info, 'service_params');

        return new YandexDeliveryApiClient([
            'auth_token' => ExternalValue::text($service_params, 'auth_token'),
            'test_mode'  => ExternalValue::text($service_params, 'test_mode') ?: YesNo::NO,
        ]);
    }

    /**
     * Checks that the shipment belongs to Yandex Delivery.
     *
     * @param array<string, string|int|float|bool|array|object|null> $shipment      Shipment data
     * @param array<string, string|int|float|bool|array|object|null> $shipping_info Shipping method data
     *
     * @return void
     *
     * @throws \Exception When shipment is not Yandex Delivery.
     */
    protected function assertYandexShipment(array $shipment, array $shipping_info)
    {
        if (
            ($shipment['carrier'] ?? '') === 'yandex_delivery'
            || ($shipping_info['module'] ?? '') === 'yandex_delivery'
        ) {
            return;
        }

        throw new Exception(__('yandex_delivery.not_yandex_delivery_shipment'));
    }

    /**
     * Generates labels PDF body.
     *
     * @param string[] $request_ids Yandex request IDs
     * @param int      $shipping_id Platform shipping ID
     *
     * @return string
     *
     * @throws \Exception When labels cannot be generated.
     */
    protected function generateLabels(array $request_ids, $shipping_id)
    {
        $shipping_info = fn_get_shipping_info($shipping_id, DESCR_SL);
        if (empty($shipping_info['service_params'])) {
            throw new Exception(__('yandex_delivery.shipping_method_not_found'));
        }

        $client = $this->createClient($shipping_info);

        return $client->generateLabels($request_ids);
    }


    /**
     * Saves PDF body to a local file.
     *
     * @param string $file_path Absolute file path
     * @param string $pdf       PDF body
     *
     * @return void
     */
    protected function savePdf($file_path, $pdf)
    {
        fn_mkdir(dirname($file_path));
        fn_put_contents($file_path, $pdf);
    }


    /**
     * Gets the directory holding the labels of one shipment.
     *
     * @param int $shipment_id Shipment ID
     *
     * @return string
     */
    protected function getShipmentLabelDir($shipment_id)
    {
        return fn_get_files_dir_path() . 'yandex_delivery/' . $shipment_id . '/';
    }

    /**
     * Removes the cached labels of the given shipments.
     *
     * A label carries the barcode of a particular request, so it must not outlive it: after a request is
     * recreated the old file would be printed and the parcel would travel under a dead barcode.
     *
     * @param int[] $shipment_ids Shipment IDs
     *
     * @return void
     */
    public function deleteShipmentLabels(array $shipment_ids)
    {
        foreach ($shipment_ids as $shipment_id) {
            fn_rm($this->getShipmentLabelDir($shipment_id));
        }
    }

    /**
     * Gets order labels file path.
     *
     * @param int      $order_id    Order ID
     * @param string[] $request_ids Yandex request IDs
     *
     * @return string
     */
    protected function getOrderLabelsPath($order_id, array $request_ids)
    {
        return fn_get_files_dir_path()
            . 'yandex_delivery/orders/'
            . $order_id
            . '/labels_'
            . md5(implode('|', $request_ids))
            . '.pdf';
    }
}
