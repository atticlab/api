<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;

class Companies extends ModelBase
{

    const BUCKET_NAME = 'companies';

    public $code;                //EDRPOU analog
    public $title;               //company name
    public $address;             //company registration address
    public $phone;               //company contact phone
    public $email;               //company contact email

    public function __construct($code)
    {

        $riak = DI::getDefault()->get('riak');

        $this->riak     = $riak;
        $this->code     = $code;

        $this->bucket   = new Bucket(self::BUCKET_NAME);
        $this->location = new Riak\Location($code, $this->bucket);
    }

    private function validate(){

        if (empty($this->code)) {
            throw new Exception(Exception::EMPTY_PARAM, 'code');
        }

        if (empty($this->title)) {
            throw new Exception(Exception::EMPTY_PARAM, 'title');
        }

        if (empty($this->address)) {
            throw new Exception(Exception::EMPTY_PARAM, 'address');
        }

        if (empty($this->phone)) {
            throw new Exception(Exception::EMPTY_PARAM, 'phone');
        }

        if (empty($this->email)) {
            throw new Exception(Exception::EMPTY_PARAM, 'email');
        }

    }

    public static function isExist($code)
    {

        $riak = DI::getDefault()->get('riak');

        $response = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('code_bin')
            ->withScalarValue($code)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return $response;

    }

    public static function getList($limit = null, $offset = null)
    {

        $riak = DI::getDefault()->get('riak');

        $companies = [];

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

        foreach ($response as $code) {

            $data = self::getDataByBucketAndID(self::BUCKET_NAME, $code);

            if (!empty($data)) {
                $companies[] = $data;
            }

        }

        return $companies;
    }

    public static function get($code){

        $data = new self($code);
        return $data->loadData();

    }

    public function create()
    {

        $this->validate();

        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName('code_bin')
            ->build()
            ->execute();

        $command = (new Command\Builder\StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        $command->getObject()->addValueToIndex('found_hack_bin', 'find_all');

        if (isset($this->code)) {
            $command->getObject()->addValueToIndex('code_bin', $this->code);
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