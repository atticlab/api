<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Bucket;
use \Basho\Riak\Command;
use App\Lib\Exception;
use Phalcon\DI;
use Smartmoney\Stellar\Account;

class IpBans extends ModelBase implements ModelInterface
{

    public $ip;
    public $banned_to;
    public $missed_for_minute;
    public $missed_for_day;
    
    public $last_request;
    
    //expects the converted ip to integer
    public function __construct($ip)
    {
        parent::__construct($ip);
        $this->ip = $ip;
    }
    
    private static function convertIpToInt($ip)
    {
        return ip2long($ip);
    }
    
    public static function checkBanned($ip){
        $ip = self::convertIpToInt($ip);
        
        try {
            $ipBan = self::findFirst($ip);
        } catch (Exception $e) {
            $ipBan = false; 
        }
        
        if (empty($ipBan) || empty($ipBan->banned_to)) {
            return false;
        }
        
        if (time() > $ipBan->banned_to) {
            $ipBan->banned_to = null;
            try {
                $ipBan->update();
            } catch (Exception $e) {
                DI::getDefault()->get('logger')->error('There is an error of delete ban.' . $e->getMessage());
            }
            return false;
        }
        
        return $ipBan->banned_to;
    }
    
    public function validate(){

        if (empty($this->ip)) {
            throw new Exception(Exception::EMPTY_PARAM, 'ip');
        }
    }

    public function create()
    {
        $command = $this->prepareCreate();
        //create secondary indexes here with addIndex method
        return $this->build($command); 
    }

    public function update()
    {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes with addIndex method
        return $this->build($command);
    }
    
    //Save or update statistics Invalid request
    public static function setMissed($ip)
    {
        $ip = self::convertIpToInt($ip);
        $now = time();
        $config = DI::getDefault()->get('config');
        $short_ban = $config['ban']['short'];
        $long_ban = $config['ban']['long'];
        $req_per_minutes = $config['ban']['req_per_minutes']; //bad requests per minute
        $req_per_day     = $config['ban']['req_per_day'];     //bad requests per day
        //if ip is not number return
        if (!is_numeric($ip)) {
            return false;
        }
        //find data by this ip
        $ipBan = self::getIpData($ip);
        $lr_minute  = $ipBan->last_request ? 
            $ipBan->last_request - ($ipBan->last_request % 60) : 0;
        $lr_day     = $ipBan->last_request ? 
            $ipBan->last_request - ($ipBan->last_request % 86400) : 0;
        $nw_minute  = $now - ($now % 60);
        $nw_day     = $now - ($now % 86400);
        //increment $missed_time
        //for minutes         
        if ($lr_minute == $nw_minute) {            
            $ipBan->missed_for_minute++;
            $ipBan->missed_for_day++;
        //for days    
        } else if($lr_day == $nw_day) {            
            $ipBan->missed_for_minute = 1;
            $ipBan->missed_for_day++;            
        //else reset   
        } else {            
            $ipBan->missed_for_minute = 1;
            $ipBan->missed_for_day = 1;
        }
        //reset missed_for_minute and add long ban
        if ($ipBan->missed_for_day >= $req_per_day) {
            $ipBan->missed_for_minute = 0;
            $ipBan->missed_for_day = 0;
            $ipBan->banned_to = $now + $long_ban;
        //reset missed_for_minute and add short ban    
        } else if ($ipBan->missed_for_minute >= $req_per_minutes) {
            $ipBan->missed_for_minute = 0;
            $ipBan->banned_to = $now + $short_ban;
        }
        $ipBan->last_request = $now;
        try {
            $ipBan->update();
        } catch (Exception $e) {
            DI::getDefault()->get('logger')->info('There is an error of update ban. ' . $e->getMessage());
        }
        
        return $ipBan;
    }
    
    //expects the converted ip to integer
    public static function getIpData($ip)
    {
        $ipBan = false;

        //if ban isset return it
        if (self::isExist($ip)) {
            try {
                $ipBan = self::findFirst($ip);
            } catch (Exception $e) {
                DI::getDefault()->get('logger')->error('There is an error of found ban. ' . $e->getMessage());
            }
        //if no ban create it   
        } else {
            $ipBan = new self($ip);
            try {
                $ipBan->create();
            } catch (Exception $e) {
                DI::getDefault()->get('logger')->error('There is an error of create new ban. ' . $e->getMessage());
            }            
        }
        
        return $ipBan;
    }
    
    //expects the ip converted to integer
    public static function removeBan($ip) 
    {
        try {        
            $obj = self::findFirst($ip);
            $obj->delete();
        } catch (Exeption $e) {
            DI::getDefault()->get('logger')->error("There is an error of remove ban " . $e->getMessage());
        }
    }
}