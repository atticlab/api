<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;

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

    public static function getDataByBucketAndID($riak, $bucket, $id) {

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

}