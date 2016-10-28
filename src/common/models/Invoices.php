<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;

class Invoices extends ModelBase implements ModelInterface
{

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

    public function __construct($id = null)
    {
        //if $id null - need to create new invoice
        if (empty($id)) {
            $id = self::generateUniqueId();
        }

        parent::__construct($id);
        $this->id = $id;
    }

    public function validate()
    {

        if (empty($this->id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'id');
        }

        if (mb_strlen($this->id) > self::UUID_LENGTH) {
            throw new Exception(Exception::BAD_PARAM, 'id');
        }

        if (empty($this->account)) {
            throw new Exception(Exception::EMPTY_PARAM, 'accountId');
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

        if (!empty($this->memo) && mb_strlen($this->memo) > self::MEMO_MAX_LENGTH) {
            throw new Exception(Exception::BAD_PARAM, 'memo');
        }

    }

    public static function findPerAccount($account, $limit = null, $offset = null)
    {

        $riak = DI::getDefault()->get('riak');

        $invoices = [];

        $object = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)->withIndexName('account_bin')
            ->withScalarValue($account);

        if (!empty($limit)) {
            $object
                ->withMaxResults($limit);
        }

        //paginator
        if (!empty($offset) && $offset > 0) {

            //get withContinuation for N page by get previos {$offset} records
            $continuation = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::$BUCKET_NAME)
                ->withIndexName('account_bin')
                ->withScalarValue($account)
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

        foreach ($response as $invoice_code) {

            $data = self::getDataByID($invoice_code);

            if (!empty($data)) {
                $invoices[] = $data;
            }

        }

        return $invoices;
    }

    //TODO: what about all invoices numbers will be used?
    public static function generateUniqueId()
    {

        //generate id
        $id = '';
        for ($i = 0; $i < self::UUID_LENGTH; $i++) {
            $id .= rand(0, 9);
        }

        //check if already exist
        if (self::isExist($id)) {
            return self::generateUniqueId();
        }

        return $id;

    }

    public function create()
    {
        $command = $this->prepareCreate($this->id);
        //create secondary indexes here with addIndex method

        if (isset($this->account)) {
            $this->addIndex($command, 'account_bin', $this->account);
        }

        return $this->build($command);

    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes with addIndex method

        if (isset($this->account)) {
            $this->addIndex($command, 'account_bin', $this->account);
        }

        return $this->build($command);
    }

}