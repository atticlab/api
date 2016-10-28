<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use Phalcon\DI;

class RegUsers extends ModelBase implements ModelInterface
{
    public $ipn_code;             //IPN code
    public $asset;                //asset
    public $surname;              //family name
    public $name;                 //user name
    public $middle_name;          //father's name
    public $email;                //email
    public $phone;                //phone
    public $address;              //address
    public $passport;             //passport series and number

    public function validate() {
        $this->validateIsAllPresent();
    }

    public function __construct($ipn_code)
    {
        parent::__construct($ipn_code);
        $this->ipn_code = $ipn_code;
    }

    public function create()
    {
        $command = $this->prepareCreate($this->ipn_code);
        return $this->build($command);
    }

    public function update() {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes
        return $this->build($command);
    }
}