<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use Phalcon\DI;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;

class Admins extends ModelBase implements ModelInterface
{
    public $account_id;       //admin account ID
    public $name_s;             //admin name
    public $position_s;         //admin position
    public $comment;            //comment

    public function validate() {
        if (empty($this->account_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'account_id');
        }
        if (!Account::isValidAccountId($this->account_id)) {
            throw new Exception(Exception::BAD_PARAM, 'account_id');
        }
        if (empty($this->name_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'name');
        }
        if (empty($this->position_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'position');
        }
    }

    public function __construct($account_id = null)
    {
        parent::__construct($account_id);
        $this->account_id = $account_id;
    }
}