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

    /**
     * ModelBase constructor.
     * @param $index -- the primary model index value
     * @throws Exception (EMPTY_PARAM)
     */
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

    /**
     * Sets this Model object from JSON data
     * @param $data -- JSON assoc array with model data
     */
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
     * Load data to model object from DB
     * @return $this
     * @throws Exception (NOT_FOUND, UNKNOWN, EMPTY_PARAM)
     */
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

    /**
     * This function add new index to model in creation or update process
     * @param $command -- Riak's command pointer (retrieved from prepare-functions)
     * @param $index_name
     * @param $index_value
     */
    public function addIndex(&$command, $index_name, $index_value)
    {
        (new StoreIndex($this->_riak))
            ->withName($index_name)
            ->build()
            ->execute();
        $command->getObject()->addValueToIndex($index_name, $index_value);
    }

    /**
     * @param $command -- Riak's command pointer (retrieved from prepare-functions)
     * @return bool -- is success value
     */
    public function build(&$command)
    {
        return $command->build()->execute()->isSuccess();
    }

    /**
     * Prepares model's create process
     * @param $primary_index_value -- this is primary (search) index value
     * @return object -- Riak's command object for next operations
     */
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

    /**
     * Prepares model's update process
     * @return object -- Riak's command object for next operations
     * @throws Exception (NOT_FOUND)
     */
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

    /**
     * Deletes this object from DB
     * @return bool -- is success value
     */
    public function delete()
    {
        return (new DeleteObject($this->_riak))
            ->atLocation($this->_location)
            ->build()
            ->execute()
            ->isSuccess();
    }

    /**
     * static method for retrieve only data by ID-index
     * @param $id -- ID model's index
     * @return bool|mixed -- assoc array with data or false
     */
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


    /**
     * Static method to check if data exists by ID-index
     * @param $id
     * @return array -- returns object
     */
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

    /**
     * A way to obtain list of model's objects from DB
     * @param null $limit
     * @param null $offset
     * @return array of objects
     */
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

    /**
     * A way to obtain only the first result (one model)
     * @param $id
     * @return mixed
     */
    public static function findFirst($id)
    {
        $class = get_called_class();
        $data  = new $class($id);

        return $data->loadData();
    }

    /**
     * Sets model's service fields as bucket name and primary index name
     */
    public static function setPrimaryAttributes()
    {
        $class_name = explode('\\', get_called_class());
        $real_class_name = mb_strtolower($class_name[count($class_name) - 1]);

        self::$BUCKET_NAME = $real_class_name;
        self::$INDEX_NAME = 'index_bin';
    }

    /** private and service functions */

    /**
     * A way to obtain all model's fields (without service BaseModel fields, which begins from '_'-symbol)
     * @return array
     */
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

    /**
     * Magic toString method, which makes string-representation of model
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->getModelProperties());
    }

    /**
     * Default validator for check if all Model's fields is filled
     * @throws Exception (EMPTY_PARAM)
     */
    protected function validateIsAllPresent()
    {
        foreach ($this->getModelProperties() as $key => $value) {
            if (empty($value)) {
                throw new Exception(Exception::EMPTY_PARAM, $key);
            }
        }
    }
}