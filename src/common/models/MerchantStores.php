<?php

namespace App\Models;

use App\Lib\Exception;
use Phalcon\DI;
use Ramsey\Uuid\Uuid;

class MerchantStores extends ModelBase
{

    public $url_s;              //url of store
    public $name_s;             //"human" name of store
    public $merchant_id_s;      //merchant account id
    public $created_i;          //timestamp
    public $store_id_s;         //store id (uuid v5)
    public $secret_key;         //secret key for verify data

    public function __construct($store_id, $url = null)
    {
        if (!empty($url)) {
            $url = self::formatUrl($url);
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                throw new Exception(Exception::BAD_PARAM, 'url');
            }
            $this->url_s = $url;
        }
        parent::__construct($store_id);
        $this->store_id_s = $store_id;
    }

    public static function generateStoreID($url)
    {
        $uuid5 = Uuid::uuid5(Uuid::NAMESPACE_DNS, $url);

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
        if (empty($this->url_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'url');
        }
        if (empty($this->name_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'name');
        }
        if (mb_strlen($this->name_s) > 20) {
            throw new Exception(Exception::BAD_PARAM, 'name');
        }
        if (empty($this->store_id_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'store_id');
        }
        if (empty($this->secret_key)) {
            throw new Exception(Exception::EMPTY_PARAM, 'secret_key');
        }
    }
}