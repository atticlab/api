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

    public function storesListAction()
    {

        $allowed_types = [
            Account::TYPE_MERCHANT
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        //get list of companies
        $limit  = $this->request->get('limit')  ?? null;
        $offset = $this->request->get('offset') ?? null;

        $result = MerchantStores::getMerchantStores($requester, $limit, $offset);

        return $this->response->items($result);

    }

    public function storesCreateAction()
    {

        $allowed_types = [
            Account::TYPE_MERCHANT
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
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

        //base64 is needed for riak!!!
        //"url" can not be used like primary key
        //because riak dont save that object (but will return success!!!)
        if (MerchantStores::isExist(base64_encode($url))) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'store');
        }

        try {
            $store = new MerchantStores($url);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

        $store->name        = $this->payload->name ?? null;

        $store->merchant_id = $requester;
        $store->store_id    = MerchantStores::generateStoreID($url);
        $store->secret_key  = MerchantStores::generateSecretKey();
        $store->date        = time();

        try {

            if ($store->create()) {
                //TODO: return data, not message
                return $this->response->single(['message' => 'success']);
            }

            $this->logger->emergency('Riak error while creating store');
            throw new Exception(Exception::SERVICE_ERROR);


        } catch (Exception $e) {

            $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function ordersListAction($store_id)
    {

        $allowed_types = [
            Account::TYPE_MERCHANT
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $limit  = $this->request->get('limit')   ?? null;
        $offset = $this->request->get('offset')  ?? null;

        //get all orders for store
        try {
            $orders = MerchantOrders::findStoreOrders($store_id, $limit, $offset);
            return $this->response->items($orders);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function ordersGetAction($order_id)
    {

        $allowed_types = [
            Account::TYPE_MERCHANT
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        try {
            $order_data = MerchantOrders::getOrder($order_id, $requester);
            return $this->response->single($order_data);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function ordersCreateAction()
    {

        $allowed_types = [
            Account::TYPE_ANONYMOUS,
            Account::TYPE_REGISTERED
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $store_id       = $this->payload->store_id ?? null;
        $amount         = $this->payload->amount ?? null;
        $currency       = $this->payload->currency ?? null;
        $order_id       = $this->payload->order_id ?? null;
        $server_url     = $this->payload->server_url ?? null;
        $success_url    = $this->payload->success_url ?? null;
        $fail_url       = $this->payload->fail_url ?? null;
        $signature      = $this->payload->signature ?? null;
        $details        = $this->payload->details ?? null;

        if (empty($store_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'store_id');
        }

        if (empty($signature)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'signature');
        }

        $store_data = MerchantStores::getDataByStoreID($store_id);

        if (empty($store_data) || empty($store_data->secret_key)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'store');
        }

        //check signature
        //build data for verify data from order
        $signature_data = [
            'store_id' => $store_id,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'order_id' => (string)$order_id,
            'details' => $details,
        ];

        ksort($signature_data);

        $verify_signature = base64_encode(hash('sha256', ($store_data->secret_key . base64_encode(json_encode($signature_data)))));

        if ($verify_signature != $signature) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'signature');
        }

        try {
            $order = new MerchantOrders();
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

        $order->store_id            = $store_id;
        $order->amount              = $amount;
        $order->currency            = $currency;
        $order->external_order_id   = $order_id;
        $order->server_url          = $server_url;
        $order->success_url         = $success_url;
        $order->fail_url            = $fail_url;
        $order->details             = $details;

        $order->date                = time();
        $order->bot_request_count   = 0;
        $order->status              = MerchantOrders::STATUS_WAIT_PAYMENT;


        //build new data and signature for answer to merchant
        $answer_data = array_merge($signature_data, ['status' => 'ok']);
        ksort($answer_data);

        $answer_signature = base64_encode(hash('sha256', ($store_data->secret_key . base64_encode(json_encode($answer_data)))));

        $server_url_data = [
            'data'      => $answer_data,
            'signature' => $answer_signature,
        ];

        $order->server_url_data = http_build_query($server_url_data);

        try {

            if ($order->create()) {

                $order_data = [
                    'store_id'          => $order->store_id,
                    'merchant_acc_id'   => $store_data->merchant_id,
                    'merchant_name'     => base64_decode($store_data->url),
                    'id'                => $order->id,
                    'amount'            => $order->amount,
                    'currency'          => $order->currency,
                    'details'           => $order->details,
                    'success_url'       => $order->success_url,
                    'fail_url'          => $order->fail_url,
                ];


                return $this->response->single($order_data);
            }

            $this->logger->emergency('Riak error while creating order');
            throw new Exception(Exception::SERVICE_ERROR);

        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

    }

}