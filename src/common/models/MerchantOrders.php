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
    public $store_id_s; //merchant id
    public $amount_f; //amount of order
    public $payment_amount; //amount of payment
    public $currency_s; //currency of order
    public $external_order_id; //id of order that was generated on merchant site
    public $details; //description of payment
    public $error_details; //description of error
    public $server_url; //url on merchant site for sending answer from merchant server
    public $success_url; //url for redirect user if payment status success
    public $fail_url; //url for redirect user if payment status fail
    public $status_i; //status of order
    public $created_i; //timestamp
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
        if (empty($this->store_id_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'store_id');
        }
        if (!MerchantStores::getDataByID($this->store_id_s)) {
            throw new Exception(Exception::BAD_PARAM, 'store_id');
        }
        if (empty($this->amount_f)) {
            throw new Exception(Exception::EMPTY_PARAM, 'amount');
        }
        if (!is_numeric($this->amount_f) || $this->amount_f <= 0) {
            throw new Exception(Exception::BAD_PARAM, 'amount');
        }
        if (empty($this->currency_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'currency');
        }
        if (empty($this->external_order_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'external_order_id');
        }
        if (empty($this->status_i)) {
            throw new Exception(Exception::EMPTY_PARAM, 'status');
        }
    }

    /**
     * @param $id - card account id
     * @return array
     */
    public static function getOrder($order_id)
    {
        if (empty($order_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'order_id');
        }
        $order_data = self::getDataByID($order_id);
        if (empty($order_data->store_id)) {
            throw new Exception(Exception::NOT_FOUND, 'order');
        }
        $store_data = MerchantStores::getDataByID($order_data->store_id);
        if (empty($store_data) || empty($store_data->merchant_id)) {
            throw new Exception(Exception::NOT_FOUND, 'store');
        }
        $order_data->store_data = $store_data;

        return (array)$order_data;
    }
}