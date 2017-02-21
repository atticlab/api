<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;

class Sms extends ModelBase implements ModelInterface
{
    public $account_id;         //user account id
    public $phone_s;            //phone
    public $otp_s;              //One-Time Pass
    public $is_confirmed_b;     //confirmation flag
    public $send_timestamp_i;   //sms send timestamp
    public $sms_id_s;           //sms id from 3th-party system

    public function __construct($account_id)
    {
        parent::__construct($account_id);
        $this->account_id = $account_id;
    }

    public function validate(){
        if (empty($this->account_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'account_id');
        }
        if (!Account::isValidAccountId($this->account_id)) {
            throw new Exception(Exception::BAD_PARAM, 'account_id');
        }
        if (empty($this->phone_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'phone');
        }
        if (empty($this->otp_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'otp');
        }
    }

    /**
     * @param $phone - user phone
     * @return array
     */
    public static function getPhoneAccountsCount($phone)
    {
        $data = self::findWithField('phone_s', $phone);
        if (empty($data)) {
            return 0;
        }

        return count((array)$data);
    }

    /**
     * @param $phone - user phone
     * @param $account_id - user account Id
     * @return boolean - is this account already confirmed
     */
    public static function checkIsConfirmed($phone, $account_id) {
        $data = self::findFirst($account_id);
        if (empty($data) || $data->phone_s != $phone) {
            return false;
        }

        return $data->is_confirmed_b;
    }
}