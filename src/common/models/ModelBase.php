<?php

namespace App\Models;

use App\Lib\Response;
use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use Basho\Riak\Command\Builder\DeleteObject;
use Basho\Riak\Command\Builder\FetchObject;
use Basho\Riak\Command\Builder\QueryIndex;
use Basho\Riak\Command\Builder\Search\StoreIndex;
use Basho\Riak\Command\Builder\StoreObject;
use App\lib\Exception;
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

    protected $BUCKET_NAME = null;
    protected $INDEX_NAME =  null;

    protected function checkConsts()
    {
        if (empty($this->BUCKET_NAME)) {
            throw new Exception(Exception::EMPTY_PARAM, 'BUCKET_NAME');
        }

        if (empty($this->INDEX_NAME)) {
            throw new Exception(Exception::EMPTY_PARAM, 'INDEX_NAME');
        }
    }

    public function __construct($index)
    {
        $this->checkConsts();
        $riak = DI::getDefault()->get('riak');

        $this->riak = $riak;

        if (empty($index)) {
            throw new Exception(Exception::EMPTY_PARAM, 'INDEX_NAME');
        }

        $this->bucket = new Bucket($this->BUCKET_NAME);
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
        $response = (new FetchObject($this->riak))
            ->atLocation($this->location)
            ->build()
            ->execute();

        if ($response->isSuccess()) {
            $this->object = $response->getObject();
        } elseif ($response->isNotFound()) {
            throw new Exception(Response::ERR_NOT_FOUND);
        } else {
            throw new Exception(Exception::UNKNOWN . ': ' . $response->getStatusCode());
        }

        if (empty($this->object)) {
            throw new Exception(Response::ERR_NOT_FOUND);
        }

        $this->setFromJSON($this->object->getData());
        return $this;
    }

    public function prepareUpdate()
    {
        $this->validate();

        if (empty($this->object)) {
            throw new Exception(Response::ERR_NOT_FOUND);
        }

        $save = $this->object->setData(json_encode($this));
        $updateCommand = (new StoreObject($this->riak))
            ->withObject($save)
            ->atLocation($this->location);

        return $updateCommand;
    }

    public function prepareCreate()
    {
        $this->validate();

        $response = (new StoreIndex($this->riak))
            ->withName($this->INDEX_NAME)
            ->build()
            ->execute();

        $response = (new StoreIndex($this->riak))
            ->withName($this->BUCKET_NAME . '_bin')
            ->build()
            ->execute();

        $command = (new StoreObject($this->riak))
            ->buildObject($this)
            ->atLocation($this->location);

        $command->getObject()->addValueToIndex($this->BUCKET_NAME . '_bin', $this->BUCKET_NAME);

        return $command;
    }

    public function delete()
    {
        $deleteCommand = (new DeleteObject($this->riak))
            ->atLocation($this->location)
            ->build();

        $result = $deleteCommand->execute();

        return $result->isSuccess();
    }

    public function getDataByID($id)
    {
        $data = false;
        $response = (new FetchObject($this->riak))
            ->buildLocation($id, $this->BUCKET_NAME)
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

    /*public function isExist($index)
    {
        $this->checkConsts();

        $response = (new QueryIndex($this->riak))
            ->buildBucket($this->BUCKET_NAME)
            ->withIndexName($this->INDEX_NAME)
            ->withScalarValue($index)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return $response;
    }*/

    public function getList($limit = null, $offset = null)
    {
        $this->checkConsts();

        $models = [];

        $object = (new QueryIndex($this->riak))
            ->buildBucket($this->BUCKET_NAME)
            ->withIndexName($this->BUCKET_NAME . '_bin')
            ->withScalarValue($this->BUCKET_NAME);

        if (!empty($limit)) {
            $object
                ->withMaxResults($limit);
        }

        //paginator
        if (!empty($offset) && $offset > 0) {

            //get withContinuation for N page by getting previous {$offset} records
            $continuation = (new QueryIndex($this->riak))
                ->buildBucket($this->BUCKET_NAME)
                ->withIndexName($this->BUCKET_NAME . '_bin')
                ->withScalarValue($this->BUCKET_NAME)
                ->withMaxResults($offset)
                ->build()
                ->execute()
                ->getContinuation();

            if (empty($continuation)) {
                return [];
            }

            $object->withContinuation($continuation);
        }

        $response = $object
            ->build()
            ->execute()
            ->getResults();

        foreach ($response as $code) {

            $data = $this->getDataByBucketAndID($this->BUCKET_NAME, $code);

            if (!empty($data)) {
                $models[] = $data;
            }

        }

        return $models;
    }
}