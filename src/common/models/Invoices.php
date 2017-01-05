<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Basho\Riak\Command\Builder\Search\FetchObjects;
use Basho\Riak\Command\Builder\FetchObject;

class Invoices extends ModelBase implements ModelInterface
{

    const UUID_LENGTH = 6;
    const MEMO_MAX_LENGTH = 14;

    public $id;
    public $account_s;
    public $expires_i;
    public $amount_f;
    public $asset_s;
    public $memo_s;
    public $requested_i; // timestamp when invoice was requested

    public $payer_s;
    public $created; // timestamp when invoice was created
    public $is_in_statistic_b;

    public function __construct($id = null)
    {
        //if $id null - need to create new invoice
        if (empty($id)) {
            $id = self::generateUniqueId();
        }
        parent::__construct($id);
        $this->id = $id;
    }

    public function validate()
    {
        if (empty($this->id)) {
            throw new Exception(Exception::EMPTY_PARAM, 'id');
        }
        if (mb_strlen($this->id) > self::UUID_LENGTH) {
            throw new Exception(Exception::BAD_PARAM, 'id');
        }
        if (empty($this->account_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'accountId');
        }
        if (empty($this->amount_f)) {
            throw new Exception(Exception::EMPTY_PARAM, 'amount');
        }
        if (!is_numeric($this->amount_f) || $this->amount_f <= 0) {
            throw new Exception(Exception::BAD_PARAM, 'amount');
        }
        if (empty($this->asset_s)) {
            throw new Exception(Exception::EMPTY_PARAM, 'asset');
        }
        if (!empty($this->memo_s) && mb_strlen($this->memo_s) > self::MEMO_MAX_LENGTH) {
            throw new Exception(Exception::BAD_PARAM, 'memo');
        }
    }

    public static function findExpiredInvoices()
    {
        self::setPrimaryAttributes();
        $items    = [];
        $riak     = DI::getDefault()->get('riak');
        $config   = DI::getDefault()->get('config');
        $response = new FetchObjects($riak);
        $response = $response
            //not in statistic
            //not requested
            //expired timestamp less than now
            ->withQuery('is_in_statistic_b:false AND requested_i:0 AND expires_i:[* TO ' . time() . ']');
        $response = $response
            ->withIndexName(self::$BUCKET_NAME . $config->riak->search_index_suffics);
        $response = $response
            ->build()
            ->execute();

        $docs = $response->getDocs();
        foreach ($docs as $item) {
            $data = false;
            $response = (new FetchObject($riak))
                ->atLocation($item->getLocation())
                ->build()
                ->execute();
            if ($response->isSuccess() && $response->getObject()) {
                $data = $response->getObject()->getData();
            }
            if (!empty($data)) {
                $items[] = $data;
            }
        }
        return self::clearYzSuffixes($items);
    }

    public static function findInvoicesInStatistic()
    {
        self::setPrimaryAttributes();
        $items    = [];
        $riak     = DI::getDefault()->get('riak');
        $config   = DI::getDefault()->get('config');
        $response = new FetchObjects($riak);
        $response = $response
            //not in statistic
            //not requested
            //expired timestamp less than now
            ->withQuery('is_in_statistic_b:true');
        $response = $response
            ->withIndexName(self::$BUCKET_NAME . $config->riak->search_index_suffics);
        $response = $response
            ->build()
            ->execute();

        $docs = $response->getDocs();
        foreach ($docs as $item) {
            $data = false;
            $response = (new FetchObject($riak))
                ->atLocation($item->getLocation())
                ->build()
                ->execute();
            if ($response->isSuccess() && $response->getObject()) {
                $data = $response->getObject()->getData();
            }
            if (!empty($data)) {
                $items[] = $data;
            }
        }
        return self::clearYzSuffixes($items);
    }

    public static function findUsedInvoices()
    {
        self::setPrimaryAttributes();
        $items    = [];
        $riak     = DI::getDefault()->get('riak');
        $config   = DI::getDefault()->get('config');
        $response = new FetchObjects($riak);
        $response = $response
            //not in statistic
            //requested timestamp not 0
            ->withQuery('is_in_statistic_b:false AND -requested_i:0');
        $response = $response
            ->withIndexName(self::$BUCKET_NAME . $config->riak->search_index_suffics);
        $response = $response
            ->build()
            ->execute();

        $docs = $response->getDocs();
        foreach ($docs as $item) {
            $data = false;
            $response = (new FetchObject($riak))
                ->atLocation($item->getLocation())
                ->build()
                ->execute();
            if ($response->isSuccess() && $response->getObject()) {
                $data = $response->getObject()->getData();
            }
            if (!empty($data)) {
                $items[] = $data;
            }
        }
        return self::clearYzSuffixes($items);
    }

    //TODO: what about all invoices numbers will be used?
    public static function generateUniqueId()
    {
        //generate id
        $id = '';
        for ($i = 0; $i < self::UUID_LENGTH; $i++) {
            $id .= rand(0, 9);
        }
        //check if already exist
        if (self::isExist($id)) {
            return self::generateUniqueId();
        }

        return $id;
    }
}