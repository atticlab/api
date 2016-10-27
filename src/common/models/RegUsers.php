<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;

class RegUsers extends ModelBase
{
    const BUCKET_NAME = 'regusers';
    const INDEX_NAME = 'ipn_code_bin';

    public $asset;                //asset
    public $surname;              //family name
    public $name;                 //user name
    public $middle_name;          //father's name
    public $email;                //email
    public $phone;                //phone
    public $address;              //address
    public $ipn_code;             //IPN code
    public $passport;             //passport series and number

    public function __construct($ipn_code)
    {
        parent::__construct($ipn_code);
        $this->ipn_code = $ipn_code;
    }


    public static function get($code)
    {
        $data = new self($code);
        return $data->loadData();
    }

    public function create()
    {
        $this->validateIsAllPresent();
        
        $command = parent::prepareCreate();

        if (isset($this->ipn_code)) {
            $command->getObject()->addValueToIndex(self::KEY_NAME, $this->ipn_code);
        }
        $response = $command->build()->execute();

        return $response->isSuccess();
    }


}