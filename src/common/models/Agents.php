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
    public $cmp_code_s;           //company code
    public $type_i;               //type of agent
    public $asset_s;              //user name
    public $created_i;            //timestamp

    public $account_id_s;         //agent account id
    public $login_s;              //login on wallet

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

        if (empty($this->type_i)) {
            throw new Exception(Exception::EMPTY_PARAM, 'type');
        }

        if (!array_key_exists($this->type_i, self::$types)) {
            throw new Exception(Exception::BAD_PARAM, 'type');
        }

        if (empty($this->cmp_code_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'company_code');
        }

        if (!Companies::isExist($this->cmp_code_s)) {
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
        $company_agents = self::findWithField('cmp_code_s', $this->cmp_code_s);
        foreach ($company_agents as $agent) {
            if ($agent->type == $this->type_i) {
                if ($agent->type != self::TYPE_MERCHANT) {
                    throw new Exception(Exception::ALREADY_EXIST, 'agent');
                }
            }
        }
        return parent::create();
    }
}