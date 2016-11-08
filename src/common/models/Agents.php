<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use Phalcon\DI;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;

class Agents extends ModelBase implements ModelInterface
{

    const ID_LENGTH = 8;

    const TYPE_MERCHANT      = 2;
    const TYPE_DISTRIBUTION  = 3;
    const TYPE_SETTLEMENT    = 4;
    const TYPE_EXCHANGE      = 5;
    // const TYPE_REPLENISHMENT; -- doesnt exist yet

    private static $types = [
        self::TYPE_MERCHANT      => 'Merchant',
        self::TYPE_DISTRIBUTION  => 'Distrubution',
        self::TYPE_SETTLEMENT    => 'Settlement',
        self::TYPE_EXCHANGE      => 'Exchange',
        // self::TYPE_REPLENISHMENT => 'Replenishment',
    ];

    public $id;
    public $cmp_code;             //company code
    public $type;                 //type of agent
    public $asset;                //user name
    public $created;              //timestamp

    public $account_id;           //agent account id
    public $login;                //login on wallet

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

        if (empty($this->type)) {
            throw new Exception(Exception::EMPTY_PARAM, 'type');
        }

        if (!array_key_exists($this->type, self::$types)) {
            throw new Exception(Exception::BAD_PARAM, 'type');
        }

        if (empty($this->cmp_code)) {
            throw new Exception(Exception::EMPTY_PARAM, 'company_code');
        }

        if (!Companies::isExist($this->cmp_code)) {
            throw new Exception(Exception::BAD_PARAM, 'company_code');
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

    public function __construct($id = null)
    {
        //if $id null - need to generate it for new agent
        if (empty($id)) {
            $id = self::generateID();
        }

        parent::__construct($id);
        $this->id = $id;
    }

    public function create()
    {
        $command = $this->prepareCreate($this->id);

        if (isset($this->cmp_code)) {
            $this->addIndex($command, 'cmp_code_bin', $this->cmp_code);
        }

        if (isset($this->type)) {
            $this->addIndex($command, 'type_bin', $this->type);
        }

        if (isset($this->account_id)) {
            $this->addIndex($command, 'account_id_bin', $this->account_id);
        }

        return $this->build($command);
    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes

        if (isset($this->cmp_code)) {
            $this->addIndex($command, 'cmp_code_bin', $this->cmp_code);
        }

        if (isset($this->type)) {
            $this->addIndex($command, 'type_bin', $this->type);
        }

        if (isset($this->account_id)) {
            $this->addIndex($command, 'account_id_bin', $this->account_id);
        }

        return $this->build($command);
    }
}