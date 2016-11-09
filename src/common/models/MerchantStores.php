<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;
use Basho\Riak\Command\Builder\FetchObject;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;


class MerchantStores extends ModelBase
{

    public $url;                //url of store
    public $name;               //"human" name of store
    public $merchant_id;        //merchant account id
    public $date;               //date of registrations
    public $store_id;           //store id (uuid v5)
    public $secret_key;         //secret key for verify data

    public function __construct($url)
    {
        $url = self::formatUrl($url);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new Exception(Exception::BAD_PARAM, 'url');
        }

        //base64 is needed for riak!!!
        //"url" can not be used like primary key
        //because riak dont save that object (but will return success!!!)
        $url = base64_encode($url);

        parent::__construct($url);
        $this->url = $url;
    }

    public static function generateStoreID($string)
    {
        $uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, $string);
        return $uuid5->toString();
    }    
    
    public static function generateSecretKey($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function formatUrl($url) {

        if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
            $url = "http://" . $url;
        }

        $url = rtrim($url, '/');
        return $url;
    }

    public function validate(){

        if (empty($this->url)) {
            throw new Exception(Exception::EMPTY_PARAM, 'url');
        }

        if (empty($this->name)) {
            throw new Exception(Exception::EMPTY_PARAM, 'name');
        }

        if (mb_strlen($this->name) > 20) {
            throw new Exception(Exception::BAD_PARAM, 'name');
        }

        if (empty($this->date)) {
            throw new Exception(Exception::EMPTY_PARAM, 'date');
        }

        if (empty($this->store_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'store_id');
        }

        if (empty($this->secret_key)) {
            throw new Exception(Exception::EMPTY_PARAM, 'secret_key');
        }

    }

    public static function getMerchantStores($merchant_id, $limit = null, $offset = null)
    {

        $riak = DI::getDefault()->get('riak');

        $companies = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)
            ->withIndexName('merchant_id_bin')
            ->withScalarValue($merchant_id);

        if (!empty($limit)) {
            $object
                ->withMaxResults($limit);
        }

        //paginator
        if (!empty($offset) && $offset > 0) {

            //get withContinuation for N page by getting previous {$offset} records
            $continuation = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::$BUCKET_NAME)
                ->withIndexName('merchant_id_bin')
                ->withScalarValue($merchant_id)
                ->withMaxResults($offset)
                ->build()
                ->execute()
                ->getContinuation();

            if (empty($continuation)) {
                return [];
            }

            $object
                ->withContinuation($continuation);
        }

        $response = $object
            ->build()
            ->execute()
            ->getResults();

        foreach ($response as $code) {

            $data = self::getDataByID($code);

            if (!empty($data)) {
                $companies[] = $data;
            }

        }

        return $companies;
    }

//    /**
//     * @param $id - card account id
//     * @return array
//     */
//    public static function getSingle($account_id, $agent_id)
//    {
//
//        $data = self::getDataByBucketAndID(self::BUCKET_NAME, $account_id);
//
//        if (!empty($data->agent_id) && $data->agent_id != $agent_id) {
//            return false;
//        }
//
//        return (array)$data;
//    }

    public static function getDataByStoreID($id)
    {

        self::setPrimaryAttributes();
        $riak = DI::getDefault()->get('riak');

        $url = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)
            ->withIndexName('store_id_bin')
            ->withScalarValue($id)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        if (empty($url[0])){
            return false;
        }

        return self::getDataByID($url[0]);

    }

    public function create()
    {

        $command = $this->prepareCreate($this->url);
        //create secondary indexes here with addIndex method

        if (isset($this->merchant_id)) {
            $this->addIndex($command, 'merchant_id_bin', $this->merchant_id);
        }

        if (isset($this->store_id)) {
            $this->addIndex($command, 'store_id_bin', $this->store_id);
        }

        return $this->build($command);
    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes with addIndex method

        if (isset($this->merchant_id)) {
            $this->addIndex($command, 'merchant_id_bin', $this->merchant_id);
        }

        if (isset($this->store_id)) {
            $this->addIndex($command, 'store_id_bin', $this->store_id);
        }

        return $this->build($command);
    }

}