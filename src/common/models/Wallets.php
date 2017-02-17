<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use \Basho\Riak\Bucket;
use App\Lib\Exception;
use Phalcon\DI;

class Wallets extends ModelBase implements ModelInterface
{
    const UNIQUE_ID_LEN = 5;

    public $username;
    public $accountId_s;
    public $walletId;
    public $salt;
    public $kdfParams;
    public $publicKey;
    public $mainData;
    public $keychainData;
    public $createdAt;
    public $updatedAt;
    public $lockVersion;

    public $totpRequired = false; //old stellar-wallet appendicitis

    public $phone_s;
    public $email_s;

    public $HDW;

    public function __construct($username) {
        parent::__construct($username);
        $this->username = $username;
    }

    public function validate() {
        if (empty($this->username)) {
            throw new Exception(Exception::EMPTY_PARAM, 'username');
        }
        //other fields use advanced validators instead of this
    }
}