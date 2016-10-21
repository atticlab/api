<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Response;

class Invoices extends ModelBase
{

    const BUCKET_NAME = 'invoices';
    const UUID_LENGTH = 6;
    const MEMO_MAX_LENGTH = 14;

    public $id;
    public $account;
    public $expires;
    public $amount;
    public $asset;
    public $memo;
    public $requested; // timestamp when invoice was requested

    public $payer;
    public $created; // timestamp when invoice was created
    public $is_in_statistic;

    public function __construct(Riak $riak, $id = null)
    {
        $this->riak = $riak;
        $this->bucket = new Bucket(self::BUCKET_NAME);

        //if need to create new invoice
        if (empty($id)) {
            $id = self::generateUniqueId($riak);
        }

        $this->id = $id;
        $this->location = new Riak\Location($id, $this->bucket);
    }

    public function __toString()
    {
        return json_encode([
            'id'                => $this->id,
            'account'           => $this->account,
            'payer'             => $this->payer,
            'expires'           => $this->expires,
            'amount'            => $this->amount,
            'asset'             => $this->asset,
            'memo'              => $this->memo,
            'requested'         => $this->requested,
            'created'       => $this->created,
            'is_in_statistic'   => $this->is_in_statistic
        ]);
    }

    private function validate()
    {

        if (empty($this->id)) {
            throw new \Exception('id', Response::ERR_EMPTY_PARAM);
        }

        if (mb_strlen($this->id) > self::UUID_LENGTH) {
            throw new \Exception('id', Response::ERR_BAD_PARAM);
        }

        if (empty($this->account)) {
            throw new \Exception('accountId', Response::ERR_EMPTY_PARAM);
        }

        if (empty($this->amount)) {
            throw new \Exception('amount', Response::ERR_EMPTY_PARAM);
        }

        if (!is_numeric($this->amount) || $this->amount <= 0) {
            throw new \Exception('amount', Response::ERR_BAD_PARAM);
        }

        if (empty($this->asset)) {
            throw new \Exception('asset', Response::ERR_EMPTY_PARAM);
        }

        if (!empty($this->memo) && mb_strlen($this->memo) > self::MEMO_MAX_LENGTH) {
            throw new \Exception('amount', Response::ERR_BAD_PARAM);
        }

    }

    public static function isExist($riak, $id)
    {

        $response = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('id_bin')
            ->withScalarValue($id)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return !empty($response);

    }

    public static function get(Riak $riak, $id){

        $data = new self($riak, $id);
        return $data->loadData();

    }

    public static function getlist($riak, $account = null, $count = null, $page = null)
    {

        $invoices = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('id_bin')
            ->withRangeValue(str_repeat('0', self::UUID_LENGTH), str_repeat('9', self::UUID_LENGTH));

        if (!empty($count)) {
            $object
                ->withMaxResults($count);
        }

        //paginator
        if (!empty($count) && !empty($page) && $page > 1) {

            //get withContinuation for N page by getting previous (page-1)*count records
            $continuation = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::BUCKET_NAME)
                ->withIndexName('id_bin')
                ->withRangeValue(str_repeat('0', self::UUID_LENGTH), str_repeat('9', self::UUID_LENGTH))
                ->withMaxResults(($page-1)*$count)
                ->build()
                ->execute()
                ->getContinuation();

            if (empty($continuation)) {
                return false;
            }

            $object
                ->withContinuation($continuation);
        }

        $response = $object
            ->build()
            ->execute()
            ->getResults();

        foreach ($response as $invoice_code) {

            $data = self::getDataByBucketAndID($riak, self::BUCKET_NAME, $invoice_code);

            if (!empty($data)) {
                $invoices[] = $data;
            }

        }

        return $invoices;
    }

    public static function getlistperaccount($riak, $account, $count = null, $page = null)
    {

        $invoices = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)->withIndexName('account_bin')
            ->withScalarValue($account);


        if (!empty($count)) {
            $object
                ->withMaxResults($count);
        }

        //paginator
        if (!empty($count) && !empty($page) && $page > 1) {

            //get withContinuation for N page by get previos (page-1)*count records
            $continuation = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::BUCKET_NAME)
                ->withIndexName('account_bin')
                ->withScalarValue($account)
                ->withMaxResults(($page-1)*$count)
                ->build()
                ->execute()
                ->getContinuation();

            if (empty($continuation)) {
                return false;
            }

            $object
                ->withContinuation($continuation);
        }

        $response = $object
            ->build()
            ->execute()
            ->getResults();

        foreach ($response as $invoice_code) {

            $data = self::getDataByBucketAndID($riak, self::BUCKET_NAME, $invoice_code);

            if (!empty($data)) {
                $invoices[] = $data;
            }

        }

        return $invoices;
    }

    //TODO: what about all invoices numbers will be used?
    public static function generateUniqueId($riak)
    {
        //generate id
        $id = '';
        for ($i = 0; $i < self::UUID_LENGTH; $i++) {
            $id .= rand(0, 9);
        }

        //check if already exist
        if (self::isExist($riak, $id)) {
            return self::generateUniqueId($riak);
        }

        return $id;

    }

    public function create()
    {
        $this->validate();

        //create new invoice record
        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName('id_bin')
            ->build()
            ->execute();

        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName('account_bin')
            ->build()
            ->execute();

        $command = (new Command\Builder\StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        if (isset($this->id)) {
            $command->getObject()->addValueToIndex('id_bin', $this->id);
        }

        if (isset($this->account)) {
            $command->getObject()->addValueToIndex('account_bin', $this->account);
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