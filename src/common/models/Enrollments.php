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

    const STAGE_CREATED  = 2;
    const STAGE_APPROVED = 4;
    const STAGE_DECLINED = 8;

    const TYPE_USER  = 'user';
    const TYPE_AGENT = 'agent';

    private $accepted_types = [
        self::TYPE_USER,
        self::TYPE_AGENT
    ];

    public $id;                   //enrollment id
    public $asset;                //asset
    public $stage;                //status of enr
    public $otp;                  //token
    public $expiration;           //timestamp in future
    public $account_id;           //user/agent account_id
    public $tx_trust;             //trust for asset, need for final approve by admin
    public $login;                //login of created user/agent

    public $type;                 //'user' or 'agent'
    public $target_id;            //id of user or agent

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

        if (!empty($this->account_id) && !Account::isValidAccountId($this->account_id)) {
            throw new Exception(Exception::BAD_PARAM, 'account_id');
        }

        if (empty($this->asset)) {
            throw new Exception(Exception::EMPTY_PARAM, 'asset');
        }

        if (empty($this->otp)) {
            throw new Exception(Exception::EMPTY_PARAM, 'otp');
        }

        if (empty($this->stage)) {
            throw new Exception(Exception::EMPTY_PARAM, 'stage');
        }

        if (empty($this->expiration)) {
            throw new Exception(Exception::EMPTY_PARAM, 'expiration');
        }

        if (empty($this->target_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'target_id');
        }

        if (empty($this->type)) {
            throw new Exception(Exception::EMPTY_PARAM, 'type');
        }

        if (!in_array($this->type, $this->accepted_types)) {
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

    public function create()
    {
        $command = $this->prepareCreate($this->id);

        if (isset($this->account_id)) {
            $this->addIndex($command, 'account_id_bin', $this->account_id);
        }

        if (isset($this->otp)) {
            $this->addIndex($command, 'otp_bin', $this->otp);
        }

        if (isset($this->type)) {
            $this->addIndex($command, 'type_bin', $this->type);
        }

        return $this->build($command);
    }

    public function update() {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes

        if (isset($this->account_id)) {
            $this->addIndex($command, 'account_id_bin', $this->account_id);
        }

        if (isset($this->otp)) {
            $this->addIndex($command, 'otp_bin', $this->otp);
        }

        if (isset($this->type)) {
            $this->addIndex($command, 'type_bin', $this->type);
        }

        return $this->build($command);
    }
}