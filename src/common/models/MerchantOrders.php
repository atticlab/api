<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;

class MerchantOrders extends ModelBase
{

    const STATUS_WAIT_PAYMENT = 1; //create order record in db, wait payment
    const STATUS_WAIT_ANSWER = 2; //payment complete, wait answer from merchant domain
    const STATUS_PARTIAL_PAYMENT = 3; //amount of payment is less than amount of order
    const STATUS_FAIL = 4;
    const STATUS_SUCCESS = 5;

    const ID_LENGTH = 11;

    public $id; //random 11 symbols (a-zA-Z0-9)
    public $store_id; //merchant id
    public $amount; //amount of order
    public $payment_amount; //amount of payment
    public $currency; //currency of order
    public $external_order_id; //id of order that was generated on merchant site
    public $details; //description of payment
    public $error_details; //description of error
    public $server_url; //url on merchant site for sending answer from merchant server
    public $success_url; //url for redirect user if payment status success
    public $fail_url; //url for redirect user if payment status fail
    public $status; //status of order
    public $date; //date of order create
    public $payment_date; //date of payment complete
    public $bot_request_count; //count of bot request tries
    public $server_url_data; //ready formating data for sending to order server_url
    public $payer; //account id of payer
    //public $answers_server_url; //save all answers from merchant server url
    public $tx; //transaction id

    public static function generateID(){

        do {
            $id = '';

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            for ($i = 0; $i < self::ID_LENGTH; $i++) {
                $id .= $characters[rand(0, $charactersLength - 1)];
            }
        } while (self::isExist($id));

        return $id;

    }

    public function __construct($id = null)
    {
        //if $id null - need to create new invoice
        if (empty($id)) {
            $id = self::generateID();
        }

        parent::__construct($id);
        $this->id = $id;
    }

    public function validate(){

        if (empty($this->id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'id');
        }

        if (empty($this->store_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'store_id');
        }

        if (!MerchantStores::getDataByStoreID($this->store_id)) {
            throw new Exception(Exception::BAD_PARAM, 'store_id');
        }

        if (empty($this->amount)) {
            throw new Exception(Exception::EMPTY_PARAM, 'amount');
        }

        if (!is_numeric($this->amount) || $this->amount <= 0) {
            throw new Exception(Exception::BAD_PARAM, 'amount');
        }

        if (empty($this->currency)) {
            throw new Exception(Exception::EMPTY_PARAM, 'currency');
        }

        if (empty($this->external_order_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'external_order_id');
        }

        if (empty($this->status)) {
            throw new Exception(Exception::EMPTY_PARAM, 'status');
        }

    }

    public static function findStoreOrders($store_id, $limit = null, $offset = null)
    {
        self::setPrimaryAttributes();

        $orders = [];

        $riak = DI::getDefault()->get('riak');

        $object = (new QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)
            ->withIndexName('store_id_bin')
            ->withScalarValue($store_id);

        if (!empty($limit)) {
            $object
                ->withMaxResults($limit);
        }

        //paginator
        if (!empty($offset) && $offset > 0) {

            //get withContinuation for N page by getting previous {$offset} records
            $continuation = (new QueryIndex($riak))
                ->buildBucket(self::$BUCKET_NAME)
                ->withIndexName('store_id_bin')
                ->withScalarValue($store_id)
                ->withMaxResults($offset)
                ->build()
                ->execute()
                ->getContinuation();

            if (empty($continuation)) {
                return [];
            }

            $object->withContinuation($continuation);
        }

        $response = $object
            ->build()
            ->execute()
            ->getResults();

        foreach ($response as $order) {

            $data = self::getDataByID($order);

            if (!empty($data)) {
                $orders[] = $data;
            }

        }

        return $orders;
    }

    public function create()
    {
        $command = $this->prepareCreate($this->id);
        //create secondary indexes here with addIndex method

        if (isset($this->account)) {
            $this->addIndex($command, 'store_id_bin', $this->account);
        }

        return $this->build($command);

    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes with addIndex method

        if (isset($this->account)) {
            $this->addIndex($command, 'store_id_bin', $this->account);
        }

        return $this->build($command);
    }

}