<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use Phalcon\DI;

use App\Lib\Exception;

class RegUsers extends ModelBase implements ModelInterface
{

    const ID_LENGTH = 8;

    public $id;
    public $ipn_code;             //IPN code
    public $asset;                //asset
    public $surname;              //family name
    public $name;                 //user name
    public $middle_name;          //father's name
    public $email;                //email
    public $phone;                //phone
    public $address;              //address
    public $passport;             //passport series and number

    public $account_id;           //user account id
    public $login;                //login on wallet

    public function validate() {

        //$this->validateIsAllPresent();

        if (empty($this->id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'id');
        }

        if (empty($this->ipn_code)) {
            throw new Exception(Exception::EMPTY_PARAM, 'ipn_code');
        }

        if (empty($this->asset)) {
            throw new Exception(Exception::EMPTY_PARAM, 'asset');
        }

        if (empty($this->surname)) {
            throw new Exception(Exception::EMPTY_PARAM, 'surname');
        }

        if (empty($this->name)) {
            throw new Exception(Exception::EMPTY_PARAM, 'name');
        }

        if (empty($this->middle_name)) {
            throw new Exception(Exception::EMPTY_PARAM, 'middle_name');
        }

        if (empty($this->email)) {
            throw new Exception(Exception::EMPTY_PARAM, 'email');
        }

        if (empty($this->phone)) {
            throw new Exception(Exception::EMPTY_PARAM, 'phone');
        }

        if (empty($this->address)) {
            throw new Exception(Exception::EMPTY_PARAM, 'address');
        }

        if (empty($this->passport)) {
            throw new Exception(Exception::EMPTY_PARAM, 'passport');
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(Exception::BAD_PARAM, 'email');
        }

        if (RegUsers::isExistByIndex('ipn_code', $this->ipn_code)) {
            throw new Exception(Exception::ALREADY_EXIST, 'ipn_code');
        }

        if (RegUsers::isExistByIndex('passport', $this->passport)) {
            throw new Exception(Exception::ALREADY_EXIST, 'passport');
        }

        if (RegUsers::isExistByIndex('email', $this->email)) {
            throw new Exception(Exception::ALREADY_EXIST, 'email');
        }

        if (RegUsers::isExistByIndex('phone', $this->phone)) {
            throw new Exception(Exception::ALREADY_EXIST, 'phone');
        }

    }

    public static function generateID(){

        do {
            $id = '';

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            for ($i = 0; $i < self::ID_LENGTH; $i++) {
                $id .= $characters[rand(0, $charactersLength - 1)];
            }
        } while (self::isExist($id));

        return $id;

    }

    public function __construct($id = null)
    {
        //if $id null - need to generate it for new registered user
        if (empty($id)) {
            $id = self::generateID();
        }

        parent::__construct($id);
        $this->id = $id;
    }

    public function create()
    {
        $command = $this->prepareCreate($this->id);

        if (isset($this->ipn_code)) {
            $this->addIndex($command, 'ipn_code_bin', $this->ipn_code);
        }

        if (isset($this->phone)) {
            $this->addIndex($command, 'phone_bin', $this->phone);
        }

        if (isset($this->email)) {
            $this->addIndex($command, 'email_bin', $this->email);
        }

        if (isset($this->passport)) {
            $this->addIndex($command, 'passport_bin', $this->passport);
        }

        return $this->build($command);
    }

    public function update() {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes

        if (isset($this->ipn_code)) {
            $this->addIndex($command, 'ipn_code_bin', $this->ipn_code);
        }

        if (isset($this->phone)) {
            $this->addIndex($command, 'phone_bin', $this->phone);
        }

        if (isset($this->email)) {
            $this->addIndex($command, 'email_bin', $this->email);
        }

        if (isset($this->passport)) {
            $this->addIndex($command, 'passport_bin', $this->passport);
        }

        return $this->build($command);
    }
}