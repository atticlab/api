<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use Phalcon\DI;
use Smartmoney\Stellar\Account;
use App\Lib\Exception;

class Enrollments extends ModelBase implements ModelInterface
{

    const ID_LENGTH = 8;
    const OTP_LENGTH = 16;

    const STAGE_CREATED  = 2;
    const STAGE_APPROVED = 4;
    const STAGE_DECLINED = 8;

    const TYPE_USER  = 'user';
    const TYPE_AGENT = 'agent';

    public static $accepted_types = [
        self::TYPE_USER,
        self::TYPE_AGENT
    ];

    public $id;                   //enrollment id
    public $asset_s;              //asset
    public $stage_i;              //status of enr
    public $otp_s;                //token
    public $created_i;            //timestamp date of create
    public $expiration;           //timestamp in future
    public $account_id_s;         //user/agent account_id
    public $tx_trust;             //trust for asset, need for final approve by admin
    public $login_s;              //login of created user/agent

    public $type_s;               //'user' or 'agent'
    public $target_id_s;          //id of user or agent

    public function __construct($id = null)
    {
        //if $id null - need to create new enrollment id
        if (empty($id)) {
            $id = self::generateID();
        }

        parent::__construct($id);
        $this->id = $id;
    }

    public function validate() {
        if (empty($this->id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'id');
        }
        if (mb_strlen($this->id) != self::ID_LENGTH) {
            throw new Exception(Exception::BAD_PARAM, 'id');
        }
        if (!empty($this->account_id_s) && !Account::isValidAccountId($this->account_id_s)) {
            throw new Exception(Exception::BAD_PARAM, 'account_id');
        }
        if (empty($this->asset_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'asset');
        }
        if (empty($this->otp_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'otp');
        }
        if (empty($this->stage_i)) {
            throw new Exception(Exception::EMPTY_PARAM, 'stage');
        }
        if (empty($this->expiration)) {
            throw new Exception(Exception::EMPTY_PARAM, 'expiration');
        }
        if (empty($this->target_id_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'target_id');
        }
        if (empty($this->type_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'type');
        }
        if (!in_array($this->type_s, self::$accepted_types)) {
            throw new Exception(Exception::BAD_PARAM, 'type');
        }
    }

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

    public static function generateOTP(){
        $random = new \Phalcon\Security\Random;
        do {
            $otp = $random->base64Safe(self::OTP_LENGTH);
        } while (self::isExistByField('otp_s', $otp));

        return $otp;
    }
}