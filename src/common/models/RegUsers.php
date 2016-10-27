<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use Phalcon\DI;

class RegUsers extends ModelBase implements ModelInterface
{
    protected $BUCKET_NAME = 'regusers';
    protected $INDEX_NAME =  'ipn_code_bin';

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
        $command = $this->prepareCreate();
        if (isset($this->ipn_code)) {
            $command->getObject()->addValueToIndex($this->INDEX_NAME, $this->ipn_code);
        }
        $response = $command->build()->execute();

        return $response->isSuccess();
    }

    public function update() {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes
        $response = $command->build()->execute();

        return $response->isSuccess();
    }


}