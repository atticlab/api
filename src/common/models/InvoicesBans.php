<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;

class InvoicesBans extends ModelBase implements ModelInterface
{

    public $account_id;
    public $blocked;

    public function __construct($account_id)
    {

        parent::__construct($account_id);
        $this->account_id = $account_id;
    }

    public function validate(){

        if (empty($this->account_id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($this->account_id)) {
            throw new Exception(Exception::BAD_PARAM, 'account_id');
        }

    }

    public function create()
    {
        $command = $this->prepareCreate($this->account_id);
        //create secondary indexes here with addIndex method
        return $this->build($command);

    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes with addIndex method
        return $this->build($command);
    }

}