<?php

namespace App\Models;

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
    public $amount;
    public $asset;
    public $created_date;
    public $used_date;
    public $type;
    public $is_used;
    public $agent_id;

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

        if (empty($this->amount)) {
            throw new Exception(Exception::EMPTY_PARAM, 'amount');
        }

        if (!is_numeric($this->amount) || $this->amount <= 0) {
            throw new Exception(Exception::BAD_PARAM, 'amount');
        }

        if (empty($this->asset)) {
            throw new Exception(Exception::EMPTY_PARAM, 'asset');
        }

        if (!isset($this->type)) {
            throw new Exception(Exception::EMPTY_PARAM, 'type');
        }

        if (!array_key_exists($this->type, self::$types)) {
            throw new Exception(Exception::BAD_PARAM, 'type');
        }

        if (empty($this->agent_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'agent_id');
        }

        if (!Account::isValidAccountId($this->agent_id)) {
            throw new Exception(Exception::BAD_PARAM, 'agent_id');
        }

        if (empty($this->seed)) {
            throw new Exception(Exception::EMPTY_PARAM, 'seed');
        }

    }

    public static function findAgentCards($agent_id, $limit = null, $offset = null)
    {

        $riak = DI::getDefault()->get('riak');

        $companies = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)
            ->withIndexName('agent_id_bin')
            ->withScalarValue($agent_id);

        if (!empty($limit)) {
            $object
                ->withMaxResults($limit);
        }

        //paginator
        if (!empty($offset) && $offset > 0) {

            //get withContinuation for N page by getting previous {$offset} records
            $continuation = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::$BUCKET_NAME)
                ->withIndexName('agent_id_bin')
                ->withScalarValue($agent_id)
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

    /**
     * @param $id - card account id
     * @return array
     */
    public static function getAgentCard($account_id, $agent_id)
    {

        $data = self::getDataByID($account_id);

        if (empty($data->agent_id) || $data->agent_id != $agent_id) {
            return false;
        }

        return (array)$data;
    }

    public function create()
    {
        $command = $this->prepareCreate($this->account_id);
        //create secondary indexes here with addIndex method

        if (isset($this->agent_id)) {
            $this->addIndex($command, 'agent_id_bin', $this->agent_id);
        }

        return $this->build($command);

    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes with addIndex method

        if (isset($this->agent_id)) {
            $this->addIndex($command, 'agent_id_bin', $this->agent_id);
        }

        return $this->build($command);
    }

}