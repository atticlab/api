<?php

namespace App\Models;

use \App\Lib\Exception;
use \Basho\Riak;
use \Basho\Riak\Bucket;
use Basho\Riak\Command\Builder\DeleteObject;
use Basho\Riak\Command\Builder\FetchObject;
use Basho\Riak\Command\Builder\Search\FetchObjects;
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
     * Sets this Model object from data
     * @param $data -- object with model data
     */
    protected function setFromData($data)
    {
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

        if ($response->isSuccess() && !$response->isNotFound()) {
            $this->_object = $response->getObject();
        } elseif ($response->isNotFound()) {
            throw new Exception(Exception::NOT_FOUND, 'object');
        } else {
            throw new Exception(Exception::UNKNOWN . ': ' . $response->getStatusCode());
        }

        if (empty($this->_object)) {
            throw new Exception(Exception::NOT_FOUND, 'object');
        }

        $this->setFromData($this->_object->getData());

        return $this;
    }

    /**
     * Create object
     * @return bool -- result of operation
     */
    public function create()
    {
        $this->validate();
        //validator probably can change primary attributes, we need to set it back
        self::setPrimaryAttributes();
        $command = (new StoreObject($this->_riak))
            ->buildJsonObject($this->toObject())
            ->atLocation($this->_location);
        $result = $command->build()->execute()->isSuccess();
        $this->loadData();

        return $result;
    }

    /**
     * Update object
     * @return bool -- result of operation
     * @throws Exception (NOT_FOUND)
     */
    public function update()
    {
        if (empty($this->_object)) {
            throw new Exception(Exception::NOT_FOUND);
        }
        $this->validate();
        //validator probably can change primary attributes, we need to set it back
        self::setPrimaryAttributes();
        $save = $this->_object->setData($this->toObject());
        $command = (new StoreObject($this->_riak))
            ->withObject($save)
            ->atLocation($this->_location);
        $result = $command->build()->execute()->isSuccess();
        $this->loadData();

        return $result;
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
            $data = $response->getObject()->getData();
        }

        return self::clearYzSuffixes($data);
    }


    /**
     * Static method to check if data exists by ID-index
     * @param $id
     * @return bool
     */
    public static function isExist($id)
    {
        self::setPrimaryAttributes();
        $riak = DI::getDefault()->get('riak');
        $response = (new FetchObject($riak))
            ->buildLocation($id, self::$BUCKET_NAME)
            ->build()
            ->execute();

        return $response->isSuccess() && $response->getObject();
    }

    /**
     * Static method to check if data exists by custom index
     * @param $field - name of field
     * @param $value - value of field
     * @return array - returns object
     */
    public static function isExistByField($field, $value)
    {
        self::setPrimaryAttributes();
        $riak = DI::getDefault()->get('riak');
        $config = DI::getDefault()->get('config');

        $response = (new FetchObjects($riak))
            ->withQuery($field . ':' . $value)
            ->withIndexName(self::$BUCKET_NAME . $config->riak->search_index_suffics)
            ->withMaxRows(1)
            ->build()
            ->execute();

        return $response->getNumFound();

    }

    /**
     * Static method to get object by custom field
     * @param $field - name of field
     * @param $value - value of field
     * @return mixed - object or false
     */
    public static function findFirstByField($field, $value)
    {
        self::setPrimaryAttributes();
        $riak = DI::getDefault()->get('riak');
        $config = DI::getDefault()->get('config');

        $response = (new FetchObjects($riak))
            ->withQuery($field . ':' . $value)
            ->withIndexName(self::$BUCKET_NAME . $config->riak->search_index_suffics)
            ->withMaxRows(1)
            ->build()
            ->execute();

        if ($response->getNumFound()) {
            $doc = $response->getDocs()[0];
            $data = false;
            $response = (new FetchObject($riak))
                ->atLocation($doc->getLocation())
                ->build()
                ->execute();
            if ($response->isSuccess() && $response->getObject()) {
                $data = $response->getObject()->getData();
            }

            return self::clearYzSuffixes($data);
        }

        return false;

    }

    /**
     * A way to obtain list of model's objects from DB
     * @param $limit  - limit of records
     * @param $offset - offset for pagination
     * @return array of objects
     * @throws Exception::BAD_PARAM
     */
    public static function find($limit = 25, $offset = 0, $order_field = '_yz_rk', $order_direction = 'asc')
    {
        if (!$limit) {
            $limit = 25;
        }
        if (!is_integer($limit)) {
            throw new Exception(Exception::BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            throw new Exception(Exception::BAD_PARAM, 'offset');
        }
        self::setPrimaryAttributes();
        $items = [];
        $riak = DI::getDefault()->get('riak');
        $config = DI::getDefault()->get('config');

        $response = (new FetchObjects($riak))
            ->withQuery('*:*')
            ->withIndexName(self::$BUCKET_NAME . $config->riak->search_index_suffics);
        if (!empty($offset)) {
            $response = $response->withStartRow($offset);
        }
        $response = $response
            ->withMaxRows($limit)
            ->withSortField($order_field . ' ' . $order_direction)
            ->build()
            ->execute();
        $docs = $response->getDocs();
        foreach ($docs as $item) {
            $data = false;
            $response = (new FetchObject($riak))
                ->atLocation($item->getLocation())
                ->build()
                ->execute();
            if ($response->isSuccess() && $response->getObject()) {
                $data = $response->getObject()->getData();
            }
            if (!empty($data)) {
                $items[] = $data;
            }
        }

        return self::clearYzSuffixes($items);
    }

    /**
     * A way to obtain list of model's objects from DB by field name and value
     * @param $limit  - limit of records
     * @param $offset - offset for pagination
     * @return array of objects
     * @throws Exception::BAD_PARAM
     */
    public static function findWithField(
        $field,
        $value,
        $limit = 25,
        $offset = 0,
        $order_field = '_yz_rk',
        $order_direction = 'asc',
        $strict_mode = true
    ) {
        if (!$limit) {
            $limit = 25;
        }
        if (!is_integer($limit)) {
            throw new Exception(Exception::BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            throw new Exception(Exception::BAD_PARAM, 'offset');
        }
        self::setPrimaryAttributes();
        $items = [];
        $riak = DI::getDefault()->get('riak');
        $config = DI::getDefault()->get('config');
        $response = new FetchObjects($riak);
        if ($strict_mode) {
            $response = $response
                ->withQuery($field . ':' . $value);
        } else {
            $response = $response
                ->withQuery($field . ':*' . $value . '*');
        }
        $response = $response
            ->withIndexName(self::$BUCKET_NAME . $config->riak->search_index_suffics);
        if (!empty($offset)) {
            $response = $response->withStartRow($offset);
        }
        $response = $response
            ->withMaxRows($limit)
            ->withSortField($order_field . ' ' . $order_direction)
            ->build()
            ->execute();

        $docs = $response->getDocs();
        foreach ($docs as $item) {
            $data = false;
            $response = (new FetchObject($riak))
                ->atLocation($item->getLocation())
                ->build()
                ->execute();
            if ($response->isSuccess() && $response->getObject()) {
                $data = $response->getObject()->getData();
            }
            if (!empty($data)) {
                $items[] = $data;
            }
        }

        return self::clearYzSuffixes($items);
    }

    /**
     * Function will return object with yokozuna suffixes
     * A way to obtain only the first result (one model)
     * @param $id
     * @return mixed
     * @throws Exception::EMPTY_PARAM
     */
    public static function findFirst($id)
    {
        if (empty($id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'primary_index');
        }
        $class = get_called_class();
        $data = new $class($id);

        return $data->loadData();
    }

    /**
     * Sets model's service fields as bucket name and primary index name
     */
    public static function setPrimaryAttributes()
    {
        $prefix = DEBUG_MODE ? 'debug_' : '';
        $class_name = explode('\\', get_called_class());
        $real_class_name = $prefix . mb_strtolower($class_name[count($class_name) - 1]);

        self::$BUCKET_NAME = $real_class_name;
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
     * method, which makes object-representation of model
     * @return object
     */
    public function toObject()
    {
        return (object)$this->getModelProperties();
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

    public static function clearYzSuffixes($data)
    {
        if (is_object($data)) {
            //single item
            self::clearRiakObject($data);
        } elseif (is_array($data)) {
            //array of items
            foreach ($data as &$item) {
                self::clearRiakObject($item);
            }
        }

        return $data;
    }

    private static function clearRiakObject(&$object)
    {
        $config = DI::getDefault()->get('config');
        $logger = DI::getDefault()->get('logger');
        if (!is_object($object)) {
            $logger->error('Can not clear yokozuna sufficses. Object expected, get ' . gettype($object));
            throw new Exception('Can not clear yokozuna sufficses. Object expected, get ' . gettype($object));
        }
        foreach (get_object_vars($object) as $key => $value) {
            if (mb_substr($key, -2) && mb_substr($key, 0, -2) && in_array(mb_substr($key, -2),
                    (array)$config->riak->yokozuna_sufficses)
            ) {
                unset($object->{$key});
                $object->{mb_substr($key, 0, -2)} = $value;
            }
        }
    }
}