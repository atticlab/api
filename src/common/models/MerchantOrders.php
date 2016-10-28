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

    const BUCKET_NAME = 'orders';
    const INDEX_NAME  = 'account_id_bin';

    public $field;

    public function __construct($field)
    {

        $riak = DI::getDefault()->get('riak');

        $this->riak  = $riak;
        $this->field = $field;

        $this->bucket     = new Bucket(self::BUCKET_NAME);
        $this->location   = new Riak\Location($field, $this->bucket);
    }

    public function __toString()
    {
        return json_encode([
            'field' => $this->field
        ]);
    }

    public function validate(){

        if (empty($this->field)) {
            throw new Exception(Exception::EMPTY_PARAM, 'field');
        }

    }

    public static function isExist($account_id)
    {

        $riak = DI::getDefault()->get('riak');

        $response = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName(self::INDEX_NAME)
            ->withScalarValue($account_id)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return $response;

    }

    public static function getList($agent_id, $limit = null, $offset = null)
    {

        $riak = DI::getDefault()->get('riak');

        $companies = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
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
                ->buildBucket(self::BUCKET_NAME)
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

            $data = self::getDataByBucketAndID(self::BUCKET_NAME, $code);

            if (!empty($data)) {
                $companies[] = $data;
            }

        }

        return $companies;
    }

    /**
     * @param $code
     * @return $this|bool
     */
    public static function get($account_id){

        $data = new self($account_id);
        return $data->loadData();

    }

    /**
     * @param $id - card account id
     * @return array
     */
    public static function getSingle($account_id, $agent_id)
    {

        $data = self::getDataByBucketAndID(self::BUCKET_NAME, $account_id);

        if (!empty($data->agent_id) && $data->agent_id != $agent_id) {
            return false;
        }

        return (array)$data;
    }

    public function create()
    {

        $this->validate();

        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName(self::INDEX_NAME)
            ->build()
            ->execute();

        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName('agent_id_bin')
            ->build()
            ->execute();

        $command = (new Command\Builder\StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        if (isset($this->account_id)) {
            $command->getObject()->addValueToIndex(self::INDEX_NAME, $this->account_id);
        }

        if (isset($this->agent_id)) {
            $command->getObject()->addValueToIndex('agent_id_bin', $this->agent_id);
        }

        $response = $command->build()->execute();

        return $response->isSuccess();

    }

    public function update()
    {

        $this->validate();

        if (empty($this->object)) {
            throw new \Exception('object_not_loaded');
        }

        $result = (new Command\Builder\StoreObject($this->riak))
            ->withObject($this->object->setData(json_encode($this)))
            ->atLocation($this->location)
            ->build()
            ->execute();

        $command = (new Command\Builder\StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        if (isset($this->agent_id)) {
            $command->getObject()->addValueToIndex('agent_id_bin', $this->agent_id);
        }

        $command->build()->execute();

        return $result->isSuccess();

    }

}