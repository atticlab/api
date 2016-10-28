<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use App\Lib;

class Companies extends ModelBase implements ModelInterface
{

    public $code;                //EDRPOU analog
    public $title;               //company name
    public $address;             //company registration address
    public $phone;               //company contact phone
    public $email;               //company contact email

    public function __construct($code)
    {
        parent::__construct($code);
        $this->code = $code;
    }

    public function validate() {
        $this->validateIsAllPresent();
    }

    public function create()
    {

        $command = $this->prepareCreate($this->code);
        return $this->build($command);

    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes
        return $this->build($command);
    }

}