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
        
        if (!$this->isAllowedType($requester, $allowed_types)){
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        
        $limit  = $this->request->get('limit')   ?? null;
        $offset = $this->request->get('offset')  ?? null;
        //get all bans
        $bans = IpBans::find($limit, $offset);       
        return $this->response->items($bans);    
    }
    


    
}