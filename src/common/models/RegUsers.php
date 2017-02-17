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
    public $ipn_code_s;             //IPN code
    public $asset_s;                //asset
    public $surname_s;              //family name
    public $name_s;                 //user name
    public $middle_name_s;          //father's name
    public $email_s;                //email
    public $phone_s;                //phone
    public $address_s;              //address
    public $passport_s;             //passport series and number
    public $created_i;              //timestamp

    public $account_id_s;           //user account id
    public $login_s;                //login on wallet

    public function validate() {
        if (empty($this->id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'id');
        }
        if (empty($this->ipn_code_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'ipn_code');
        }
        if (empty($this->asset_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'asset');
        }
        if (empty($this->surname_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'surname');
        }
        if (empty($this->name_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'name');
        }
        if (empty($this->middle_name_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'middle_name');
        }
        if (empty($this->email_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'email');
        }
        if (empty($this->phone_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'phone');
        }
        if (empty($this->address_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'address');
        }
        if (empty($this->passport_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'passport');
        }
        if (!filter_var($this->email_s, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(Exception::BAD_PARAM, 'email');
        }
        $id_by_ipn = RegUsers::findFirstByField('ipn_code_s', $this->ipn_code_s);
        if (!empty($id_by_ipn) && $id_by_ipn->id != $this->id) {
            throw new Exception(Exception::ALREADY_EXIST, 'ipn_code');
        }
        $id_by_passport = RegUsers::findFirstByField('passport_s', $this->passport_s);
        if (!empty($id_by_passport) && $id_by_passport->id != $this->id) {
            throw new Exception(Exception::ALREADY_EXIST, 'passport');
        }
        $id_by_email = RegUsers::findFirstByField('email_s', $this->email_s);
        if (!empty($id_by_email) && $id_by_email->id != $this->id) {
            throw new Exception(Exception::ALREADY_EXIST, 'email');
        }
        $id_by_phone = RegUsers::findFirstByField('phone_s', $this->phone_s);
        if (!empty($id_by_phone) && $id_by_phone->id != $this->id) {
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
}