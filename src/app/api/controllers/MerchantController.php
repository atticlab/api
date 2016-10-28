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
        $url = $this->payload->url  ?? null;

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
        $url = base64_encode($url);

        if (MerchantStores::isExist($url)) {
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

//    public function ordersListAction($store_id)
//    {
//
//        var_dump($store_id);
//        die('aloha');
//
//    }
//
//    public function ordersGetAction($order_id)
//    {
//
//        var_dump($order_id);
//        die('aloha');
//
//    }
//
//    public function ordersCreateAction()
//    {
//
//        die('aloha');
//
//    }

}