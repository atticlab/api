<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;

class InvoicesBans extends ModelBase
{

    const BUCKET_NAME = 'invoices_bans';

    public $account_id;
    public $blocked;

    public function __construct($account_id)
    {

        $riak = DI::getDefault()->get('riak');

        $this->riak       = $riak;
        $this->account_id = $account_id;

        $this->bucket     = new Bucket(self::BUCKET_NAME);
        $this->location   = new Riak\Location($account_id, $this->bucket);
    }

    public function __toString()
    {
        return json_encode([
            'account_id' => $this->account_id,
            'blocked'    => $this->blocked
        ]);
    }

    private function validate(){

        if (empty($this->account_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($this->account_id)) {
            throw new Exception(Exception::BAD_PARAM, 'account_id');
        }

    }

    public static function isExist($account_id)
    {

        $riak = DI::getDefault()->get('riak');

        $response = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('account_id_bin')
            ->withScalarValue($account_id)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return $response;

    }

    public static function getList($limit = null, $offset = null)
    {

        $riak = DI::getDefault()->get('riak');

        $bans = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('found_hack_bin')
            ->withScalarValue('find_all');

        if (!empty($limit)) {
            $object
                ->withMaxResults($limit);
        }

        //paginator
        if (!empty($offset) && $offset > 0) {

            //get withContinuation for N page by getting previous {$offset} records
            $continuation = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::BUCKET_NAME)
                ->withIndexName('found_hack_bin')
                ->withScalarValue('find_all')
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

        foreach ($response as $account_id) {

            $data = self::getDataByBucketAndID(self::BUCKET_NAME, $account_id);

            if (!empty($data)) {
                $bans[] = $data;
            }

        }

        return $bans;
    }

    public static function get($account_id){

        $data = new self($account_id);
        return $data->loadData();

    }

    public function create()
    {

        $this->validate();

        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName('account_id_bin')
            ->build()
            ->execute();

        $command = (new Command\Builder\StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        $command->getObject()->addValueToIndex('found_hack_bin', 'find_all');

        if (isset($this->account_id)) {
            $command->getObject()->addValueToIndex('account_id_bin', $this->account_id);
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

        return $result->isSuccess();

    }

}