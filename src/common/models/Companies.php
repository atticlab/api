<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Response;

class Companies extends ModelBase
{

    const BUCKET_NAME = 'companies';

    public $code;                //EDRPOU analog
    public $title;               //company name
    public $address;             //company registration address
    public $phone;               //company contact phone
    public $email;               //company contact email

    public function __construct(Riak $riak, $code)
    {
        $this->riak     = $riak;
        $this->code     = $code;

        $this->bucket   = new Bucket(self::BUCKET_NAME);
        $this->location = new Riak\Location($code, $this->bucket);
    }

    public function __toString()
    {
        return json_encode([
            'code'      => $this->code,
            'title'     => $this->title,
            'address'   => $this->address,
            'phone'     => $this->phone,
            'email'     => $this->email
        ]);
    }

    private function validate(){

        if (empty($this->code)) {
            throw new \Exception('code', Response::ERR_EMPTY_PARAM);
        }

        if (empty($this->title)) {
            throw new \Exception('title', Response::ERR_EMPTY_PARAM);
        }

        if (empty($this->address)) {
            throw new \Exception('address', Response::ERR_EMPTY_PARAM);
        }

        if (empty($this->phone)) {
            throw new \Exception('phone', Response::ERR_EMPTY_PARAM);
        }

        if (empty($this->email)) {
            throw new \Exception('email', Response::ERR_EMPTY_PARAM);
        }

    }

    public static function isExist(Riak $riak, $code)
    {

        $response = (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::BUCKET_NAME)
            ->withIndexName('code_bin')
            ->withScalarValue($code)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();

        return !empty($response);

    }



    public static function getList(Riak $riak, $code = null, $count = null)
    {

        $companies = [];

        if (!empty($code)) {

            $object = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::BUCKET_NAME)
                ->withIndexName('code_bin')
                ->withScalarValue($code)
                ->withMaxResults(1);

        } else {
            $object = (new Command\Builder\QueryIndex($riak))
                ->buildBucket(self::BUCKET_NAME)
                ->withIndexName('found_hack_bin')
                ->withScalarValue('find_all');

            if (!empty($count)) {
                $object
                    ->withMaxResults($count);
            }
        }

        $response = $object
            ->build()
            ->execute()
            ->getResults();

        foreach ($response as $code) {

            $data = self::getDataByBucketAndID($riak, self::BUCKET_NAME, $code);

            if (!empty($data)) {
                $companies[] = $data;
            }

        }

        return $companies;
    }

    public static function get(Riak $riak, $code){

        $data = new self($riak, $code);
        return $data->loadData();

    }

    public function create()
    {

        try {
            $this->validate();
        } catch (\Exception $e) {
            throw $e;
        }

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
        try {
            $this->validate();
        } catch (\Exception $e) {
            throw $e;
        }

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