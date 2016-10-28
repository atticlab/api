<?php

namespace App\Models;

use App\Controllers\RegusersController;
use App\Lib\Response;
use App\Lib\Exception;
use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use Basho\Riak\Command\Builder\DeleteObject;
use Basho\Riak\Command\Builder\FetchObject;
use Basho\Riak\Command\Builder\QueryIndex;
use Basho\Riak\Command\Builder\Search\StoreIndex;
use Basho\Riak\Command\Builder\StoreObject;
use Phalcon\DI;

abstract class ModelBase
{
    /**
     * @var Riak $riak
     */
    protected $_riak;
    /**
     * @var Basho\Riak\Object
     */
    protected $_object;
    /**
     * @var Riak\Bucket $bucket
     */
    protected $_bucket;
    /**
     * @var Riak\Location $location
     */
    protected $_location;

    protected static $BUCKET_NAME;
    protected static $INDEX_NAME;

    public static function setPrimaryAttributes()
    {
        $class_name = explode('\\', get_called_class());
        $real_class_name = mb_strtolower($class_name[count($class_name) - 1]);

        self::$BUCKET_NAME = $real_class_name;
        self::$INDEX_NAME = 'index_bin';
    }

    public function __construct($index)
    {
        if (empty($index)) {
            throw new Exception(Exception::EMPTY_PARAM, 'PRIMARY_INDEX');
        }

        self::setPrimaryAttributes();
        $riak = DI::getDefault()->get('riak');
        $this->_riak = $riak;
        $this->_bucket = new Bucket(self::$BUCKET_NAME);
        $this->_location = new Riak\Location($index, $this->_bucket);

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

    public function loadData()
    {
        $response = (new FetchObject($this->_riak))
            ->atLocation($this->_location)
            ->build()
            ->execute();

        if ($response->isSuccess()) {
            $this->_object = $response->getObject();
        } elseif ($response->isNotFound()) {
            throw new Exception(Exception::NOT_FOUND);
        } else {
            throw new Exception(Exception::UNKNOWN . ': ' . $response->getStatusCode());
        }

        if (empty($this->_object)) {
            throw new Exception(Exception::EMPTY_PARAM, 'object');
        }

        $this->setFromJSON($this->_object->getData());
        return $this;
    }

    public function addIndex(&$command, $index_name, $index_value)
    {
        (new StoreIndex($this->_riak))
            ->withName($index_name)
            ->build()
            ->execute();
        $command->getObject()->addValueToIndex($index_name, $index_value);
    }

    public function build($command)
    {
        return $command->build()->execute()->isSuccess();
    }

    public function prepareCreate($primary_index_value)
    {

        $this->validate();
        $found_all_idx = self::$BUCKET_NAME . '_bin'; //Riak hack for found all from bucket
        $command = (new StoreObject($this->_riak))
            ->buildObject($this)
            ->atLocation($this->_location);
        $this->addIndex($command, $found_all_idx, self::$BUCKET_NAME);
        $this->addIndex($command, self::$INDEX_NAME, $primary_index_value);
        return $command;
    }

    public function prepareUpdate()
    {
        if (empty($this->_object)) {
            throw new Exception(Exception::NOT_FOUND);
        }

        $this->validate();
        $save = $this->_object->setData(json_encode($this));
        $command = (new StoreObject($this->_riak))
            ->withObject($save)
            ->atLocation($this->_location);
        return $command;
    }

    public function delete()
    {
        return (new DeleteObject($this->_riak))
            ->atLocation($this->_location)
            ->build()
            ->execute()
            ->isSuccess();
    }

    public static function getDataByID($id)
    {
        self::setPrimaryAttributes();
        $data = false;
        $riak = DI::getDefault()->get('riak');
        $response = (new FetchObject($riak))
            ->buildLocation($id, self::$BUCKET_NAME)
            ->build()
            ->execute();
        if ($response->isSuccess() && $response->getObject()) {
            $data = json_decode($response->getObject()->getData());
        }

        return $data;
    }

    public static function isExist($id)
    {
        self::setPrimaryAttributes();
        $riak = DI::getDefault()->get('riak');
        return (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)
            ->withIndexName(self::$INDEX_NAME)
            ->withScalarValue($id)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();
    }

    public static function find($limit = null, $offset = null)
    {
        self::setPrimaryAttributes();
        $models = [];
        $riak = DI::getDefault()->get('riak');
        $object = (new QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)
            ->withIndexName(self::$BUCKET_NAME . '_bin')
            ->withScalarValue(self::$BUCKET_NAME);

        if (!empty($limit)) {
            $object
                ->withMaxResults($limit);
        }

        //paginator
        if (!empty($offset) && $offset > 0) {

            //get withContinuation for N page by getting previous {$offset} records
            $continuation = (new QueryIndex($riak))
                ->buildBucket(self::$BUCKET_NAME)
                ->withIndexName(self::$BUCKET_NAME . '_bin')
                ->withScalarValue(self::$BUCKET_NAME)
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

            $data = self::getDataByID($code);

            if (!empty($data)) {
                $models[] = $data;
            }

        }

        return $models;
    }

    public static function findFirst($id)
    {
        $class = get_called_class();
        $data  = new $class($id);

        return $data->loadData();
    }

    private function getModelProperties()
    {
        $data = [];
        foreach (get_object_vars($this) as $key => $value) {
            if (strpos($key, '_') !== 0) {
                $data[$key] = $value;
            }
        }
        return $data;
    }

    public function __toString() //TODO ??
    {
        return json_encode($this->getModelProperties());
    }

    protected function validateIsAllPresent()
    {
        foreach ($this->getModelProperties() as $key => $value) {
            if (empty($value)) {
                throw new Exception(Exception::EMPTY_PARAM, $key);
            }
        }
    }
}