<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\IpBans;
use Smartmoney\Stellar\Account;

class BansController extends ControllerBase
{
    public function bansAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        
        $limit  = $this->request->get('limit')   ?? null;
        $offset = $this->request->get('offset')  ?? null;
        //get all bans
        $bans = IpBans::find($limit, $offset);
        return $this->response->items($bans);
    }
    
    public function addAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        
        $banned_to = $this->request->get('banned_to');
        $ip = $this->request->get('ip');
        
        if (!empty($banned_to) and !empty($ip)) {            
            $ip = ip2long($ip);
            $ip_ban = IpBans::getIpData($ip);
            $ip_ban->banned_to = $banned_to;
            
            try {
                $ip_ban->update();
            } catch (Exeption $e) {
                $this->logger->error('Failed to create/update ip ban -> ' . $e->getMessage());
            }
        }
    }
    
    public function deleteAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        
        $ip = $this->request->get('ip');
        if (!empty($ip)) {
            $ip = ip2long($ip);
            IpBans::removeBan($ip);
        }
    }
}