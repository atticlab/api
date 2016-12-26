<?php

namespace App\Models;

use App\Lib\Response;
use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;

class Cards extends ModelBase implements ModelInterface
{

    const TYPE_PREPAID  = 0;
    const TYPE_CREDIT   = 1;

    private static $types = [
        self::TYPE_PREPAID => 'Prepaid Card',
        self::TYPE_CREDIT  => 'Credit Card',
    ];

    public $account_id;          //card account id
    public $seed;                //card crypt seed (sjcl lib) by agent password
    public $amount_f;
    public $asset_s;
    public $created_date_i;
    public $used_date;
    public $type_i;
    public $is_used_b;
    public $agent_id_s;

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
        if (empty($this->amount_f)) {
            throw new Exception(Exception::EMPTY_PARAM, 'amount');
        }
        if (!is_numeric($this->amount_f) || $this->amount_f <= 0) {
            throw new Exception(Exception::BAD_PARAM, 'amount');
        }
        if (empty($this->asset_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'asset');
        }
        if (!isset($this->type_i)) {
            throw new Exception(Exception::EMPTY_PARAM, 'type');
        }
        if (!array_key_exists($this->type_i, self::$types)) {
            throw new Exception(Exception::BAD_PARAM, 'type');
        }
        if (empty($this->agent_id_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'agent_id');
        }
        if (!Account::isValidAccountId($this->agent_id_s)) {
            throw new Exception(Exception::BAD_PARAM, 'agent_id');
        }
        if (empty($this->seed)) {
            throw new Exception(Exception::EMPTY_PARAM, 'seed');
        }
    }

    /**
     * @param $id - card account id
     * @return array
     */
    public static function getAgentCard($account_id, $agent_id)
    {
        $data = self::getDataByID($account_id);
        if (empty($data->agent_id) || $data->agent_id != $agent_id) {
            return [];
        }

        return (array)$data;
    }
}