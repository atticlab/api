<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;

class InvoiceBans extends ModelBase
{

    const BUCKET_NAME = 'invoice_bans';

    public $accountId;
    public $blocked;

    public function __construct($accountId)
    {

        $riak = DI::getDefault()->get('riak');

        $this->riak          = $riak;
        $this->accountId     = $accountId;

        $this->bucket   = new Bucket(self::BUCKET_NAME);
        $this->location = new Riak\Location($accountId, $this->bucket);
    }

    public function __toString()
    {
        return json_encode([
            'accountId' => $this->accountId,
            'blocked'   => $this->blocked
        ]);
    }

    private function validate(){

        if (empty($this->accountId)) {
            throw new Exception(Exception::EMPTY_PARAM, 'accountId');
        }

        if (!Account::isValidAccountId($this->accountId)) {
            throw new Exception(Exception::BAD_PARAM, 'accountId');
        }

    }

    public static function isExist($accountId)
    {

        $riak = DI::getDefault()->get('riak');

        $response = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('accountId_bin')
            ->withScalarValue($accountId)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return $response;

    }

    public static function getList($count = null, $page = null)
    {

        $riak = DI::getDefault()->get('riak');

        $companies = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('found_hack_bin')
            ->withScalarValue('find_all');

        if (!empty($count)) {
            $object
                ->withMaxResults($count);
        }

        //paginator
        if (!empty($count) && !empty($page) && $page > 1) {

            //get withContinuation for N page by getting previous (page-1)*count records
            $continuation = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::BUCKET_NAME)
                ->withIndexName('found_hack_bin')
                ->withScalarValue('find_all')
                ->withMaxResults(($page-1)*$count)
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

    public static function get($accountId){

        $data = new self($accountId);
        return $data->loadData();

    }

    public function create()
    {

        $this->validate();

        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName('accountId_bin')
            ->build()
            ->execute();

        $command = (new Command\Builder\StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        $command->getObject()->addValueToIndex('found_hack_bin', 'find_all');

        if (isset($this->accountId)) {
            $command->getObject()->addValueToIndex('accountId_bin', $this->accountId);
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