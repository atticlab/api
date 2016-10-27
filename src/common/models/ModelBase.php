<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use Phalcon\DI;

class ModelBase
{
    /**
     * @var Riak $riak
     */
    protected $riak;
    /**
     * @var Basho\Riak\Object
     */
    protected $object;
    /**
     * @var Riak\Bucket $bucket
     */
    protected $bucket;
    /**
     * @var Riak\Location $location
     */
    protected $location;

    protected function checkConsts()
    {
        if (empty(self::BUCKET_NAME)) {
            throw new Exception(Exception::EMPTY_PARAM, 'BUCKET_NAME');
            return;
        }

        if (empty(self::INDEX_NAME)) {
            throw new Exception(Exception::EMPTY_PARAM, 'INDEX_NAME');
            return;
        }
    }

    public function __construct($index)
    {
        $riak = DI::getDefault()->get('riak');

        $this->riak = $riak;

        $this->bucket = new Bucket(self::BUCKET_NAME);
        $this->location = new Riak\Location($index, $this->bucket);
    }

    protected function setFromJSON($data)
    {
        $data = json_decode($data);
        foreach ($data AS $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Loads data from RIAK and populates object with values
     *
     **/
    public function loadData()
    {

        $response = (new Command\Builder\FetchObject($this->riak))
            ->atLocation($this->location)
            ->build()
            ->execute();

        if ($response->isSuccess() && $response->getObject()) {

            $this->object = $response->getObject();

            $this->setFromJSON($this->object->getData());
            $this->lockVersion = $this->object->getVclock();
            return $this;

        }

        return false;

    }

    public function update()
    {
        $this->validateIsAllPresent();

        if (empty($this->object)) {
            throw new \Exception('object_not_loaded');
        }

        $save = $this->object->setData(json_encode($this));
        $updateCommand = (new Command\Builder\StoreObject($this->riak))
            ->withObject($save)
            ->atLocation($this->location)
            ->build();

        $result = $updateCommand->execute();

        return $result->isSuccess();
    }

    public function delete()
    {
        $deleteCommand = (new Command\Builder\DeleteObject($this->riak))
            ->atLocation($this->location)
            ->build();

        $result = $deleteCommand->execute();

        return $result->isSuccess();

    }

    public static function getDataByBucketAndID($bucket, $id)
    {
        $riak = DI::getDefault()->get('riak');

        $data = false;

        $response = (new \Basho\Riak\Command\Builder\FetchObject($riak))
            ->buildLocation($id, $bucket)
            ->build()
            ->execute();

        if ($response->isSuccess() && $response->getObject()) {
            $data = json_decode($response->getObject()->getData());
        }

        return $data;

    }

    public function __toString()
    {
        return json_encode(get_object_vars($this));
    }

    protected function validateIsAllPresent()
    {
        foreach (get_object_vars($this) as $key => $value) {
            if (empty($value)) {
                throw new Exception(Exception::EMPTY_PARAM, $key);
            }
        }
    }

    public static function isExist($index)
    {
        self::checkConsts();

        $riak = DI::getDefault()->get('riak');

        $response = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName(self::INDEX_NAME)
            ->withScalarValue($index)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return $response;
    }

    public static function getList($limit = null, $offset = null)
    {
        self::checkConsts();

        $riak = DI::getDefault()->get('riak');

        $models = [];

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
                $models[] = $data;
            }

        }

        return $models;
    }

    public function prepareCreate()
    {
        $response = (new \Basho\Riak\Command\Builder\Search\StoreIndex($this->riak))
            ->withName(self::KEY_NAME)
            ->build()
            ->execute();

        $command = (new Command\Builder\StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        $command->getObject()->addValueToIndex('found_hack_bin', 'find_all');

        return $command;
    }
}