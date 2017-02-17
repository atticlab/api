<?php

namespace App\Models;

use App\Lib\Exception;
use Phalcon\DI;

class Companies extends ModelBase implements ModelInterface
{
    public $code_s;                //EDRPOU analog
    public $title_s;               //company name
    public $address_s;             //company registration address
    public $phone_s;               //company contact phone
    public $email_s;               //company contact email
    public $created_date_i;        //timestamp of registration

    public function __construct($code) {
        parent::__construct($code);
        $this->code_s = $code;
    }

    public function validate() {
        $this->validateIsAllPresent();
        if (Companies::isExist($this->code_s)) {
            throw new Exception(Exception::ALREADY_EXIST, 'company_code');
        }
        if (!filter_var($this->email_s, FILTER_VALIDATE_EMAIL)) {
            throw new Exception(Exception::BAD_PARAM, 'email');
        }
        if (Companies::isExistByField('email_s', $this->email_s)) {
            throw new Exception(Exception::ALREADY_EXIST, 'company_email');
        }
    }
}