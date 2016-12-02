<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;

class InvoicesStatistic extends ModelBase implements ModelInterface
{

    public $date; //unix timestamp of day start (time()-time()/86400)
    public $expired; //count of expired
    public $used; //count of used
    public $all; //count of created

    public function __construct($date = null)
    {
        if (empty($date)) {
            $date = time()-time()/86400;
        }

        parent::__construct($date);
        $this->date = $date;
    }

    public function validate()
    {
        if (empty($this->date)) {
            throw new Exception(Exception::EMPTY_PARAM, 'date');
        }
    }

    public function create()
    {
        $command = $this->prepareCreate($this->date);
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