<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\MerchantStores;
use App\Models\MerchantOrders;
use App\Lib\Exception;
use GuzzleHttp\Tests\Psr7\Str;
use Smartmoney\Stellar\Account;

class MerchantController extends ControllerBase
{

    private $currency_map = [
        'UAH' => 'EUAH'
    ];

    public function storesListAction()
    {
        $allowed_types = [
            Account::TYPE_MERCHANT
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        //get list of companies
        $limit = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }
        $result = MerchantStores::findWithField('merchant_id_s', $requester, $limit, $offset, 'created_i', 'desc');

        return $this->response->json($result);
    }

    public function storesCreateAction()
    {
        $allowed_types = [
            Account::TYPE_MERCHANT
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        // Create new store
        $url = $this->payload->url ?? null;
        if (empty($url)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'url');
        }
        $url = MerchantStores::formatUrl($url);
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'url');
        }
        $store_id = MerchantStores::generateStoreID($url);
        if (MerchantStores::isExist($store_id)) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'store');
        }
        try {
            $store = new MerchantStores($store_id, $url);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
        $store->name_s = $this->payload->name ?? null;
        $store->merchant_id_s = $requester;
        $store->secret_key = MerchantStores::generateSecretKey();
        $store->created_i = time();
        try {
            if ($store->create()) {
                return $this->response->json();
            }
            $this->logger->emergency('Riak error while creating store');
            throw new Exception(Exception::SERVICE_ERROR);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function ordersListAction()
    {
        $allowed_types = [
            Account::TYPE_MERCHANT
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $limit = intval($this->request->get('limit')) ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }

        $store_id = $this->payload->store_id;
        if (empty($store_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'store_id');
        }
        //get all orders for store
        try {
            $orders = MerchantOrders::findWithField('store_id_s', $store_id, $limit, $offset, 'created_i', 'desc');

            return $this->response->json($orders);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    //for compability on period of sdk migration
    public function tempOrdersListAction($store_id)
    {
        $allowed_types = [
            Account::TYPE_MERCHANT
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $limit = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }
        if (empty($store_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'store_id');
        }
        //get all orders for store
        try {
            $orders = MerchantOrders::findWithField('store_id_s', $store_id, $limit, $offset, 'created_i', 'desc');

            return $this->response->json($orders);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function ordersGetAction($order_id)
    {
        try {
            $order_data = MerchantOrders::getOrder($order_id);

            return $this->response->json($order_data);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function ordersCreateAction()
    {
        $store_id = $this->request->getPost('store_id');
        $amount = floatval(number_format($this->request->getPost('amount'), 2, '.', ''));
        $currency = $this->request->getPost('currency');
        $order_id = $this->request->getPost('order_id');
        $server_url = $this->request->getPost('server_url');
        $success_url = $this->request->getPost('success_url');
        $fail_url = $this->request->getPost('fail_url');
        $details = $this->request->getPost('details');
        $signature = $this->request->getPost('signature');

        if (empty($store_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'store_id');
        }

        if (empty($currency)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'currency');
        }

        if (!array_key_exists(mb_strtoupper($currency), $this->currency_map)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'currency');
        }

        if (empty($signature)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'signature');
        }

        $store_data = MerchantStores::getDataByID($store_id);
        if (empty($store_data) || empty($store_data->secret_key)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'store');
        }

        $store_url_host = parse_url($store_data->url, PHP_URL_HOST);

        $server_url = MerchantStores::formatUrl($server_url);
        $success_url = MerchantStores::formatUrl($success_url);
        $fail_url = MerchantStores::formatUrl($fail_url);

        $store_url_host = MerchantStores::formatUrl($store_url_host);

        //verify urls (server, success, fail) for valid and merchant domain belong

        //server url
        if (filter_var($server_url, FILTER_VALIDATE_URL) === false) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'server_url');
        }

        if (parse_url($server_url, PHP_URL_SCHEME) != parse_url($store_url_host, PHP_URL_SCHEME)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'server_url');
        }

        if (parse_url($server_url, PHP_URL_HOST) != parse_url($store_url_host, PHP_URL_HOST)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'server_url');
        }

        //success url
        if (filter_var($success_url, FILTER_VALIDATE_URL) === false) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'success_url');
        }

        if (parse_url($success_url, PHP_URL_SCHEME) != parse_url($store_url_host, PHP_URL_SCHEME)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'success_url');
        }

        if (parse_url($success_url, PHP_URL_HOST) != parse_url($store_url_host, PHP_URL_HOST)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'success_url');
        }

        //fail url
        if (filter_var($fail_url, FILTER_VALIDATE_URL) === false) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'fail_url');
        }

        if (parse_url($fail_url, PHP_URL_SCHEME) != parse_url($store_url_host, PHP_URL_SCHEME)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'fail_url');
        }

        if (parse_url($fail_url, PHP_URL_HOST) != parse_url($store_url_host, PHP_URL_HOST)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'fail_url');
        }

        //check signature
        //build data for verify data from order
        $signature_data = [
            'store_id' => $store_id,
            'amount'   => $amount,
            'currency' => $currency,
            'order_id' => (string)$order_id,
            'details'  => $details,
        ];

        ksort($signature_data);

        $verify_signature = base64_encode(hash('sha256',
            ($store_data->secret_key . base64_encode(json_encode($signature_data)))));

        if ($verify_signature != $signature) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'signature');
        }

        try {
            $order = new MerchantOrders();
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $currency = $this->currency_map[$currency];

        $order->store_id_s = $store_id;
        $order->amount_f = $amount;
        $order->currency_s = $currency;
        $order->external_order_id = $order_id;
        $order->server_url = $server_url;
        $order->success_url = $success_url;
        $order->fail_url = $fail_url;
        $order->details = $details;

        $order->created_i = time();
        $order->bot_request_count = 0;
        $order->status_i = MerchantOrders::STATUS_WAIT_PAYMENT;

        //build new data and signature for answer to merchant
        $answer_data = array_merge($signature_data, ['status' => 'ok']);
        ksort($answer_data);

        $answer_data = base64_encode(json_encode($answer_data));
        $answer_signature = base64_encode(hash('sha256', ($store_data->secret_key . $answer_data)));

        $server_url_data = [
            'data'      => $answer_data,
            'signature' => $answer_signature,
        ];

        $order->server_url_data = http_build_query($server_url_data);

        try {
            if ($order->create()) {
                return $this->response->redirect(rtrim($this->config->merchant->transaction_url,
                        '/') . '/' . $order->id, true);
            }
            $this->logger->emergency('Riak error while creating order');
            throw new Exception(Exception::SERVICE_ERROR);

        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

}