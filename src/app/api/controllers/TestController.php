<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\IpBans;
use Smartmoney\Stellar\Account;

class BansController extends ControllerBase
{
    public function listAction()
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

    public function manageAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $banned_for = $this->payload->banned_for ?? null;
        $ip         = $this->payload->ip         ?? null;

        if (empty($ip)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'ip');
        }

        $int_ip = ip2long($ip);
        $now = time();
        $banned_to = $now + ($banned_for * 60);
        
        //remove ban
        if (empty($banned_for)) {
            if (!IpBans::removeBan($int_ip)) {
                $this->logger->info('Failed to remove ip: ' . $ip);
                return $this->response->error(Response::ERR_SERVICE, 'Failed to remove ip: ' . $ip);
            }

            $this->logger->info('Remove ban ip: '. $ip);
        //create ban
        } else {
            $ip_ban = IpBans::getIpData($int_ip);
            $ip_ban->banned_to = $banned_to;

            try {
                $ip_ban->update();
                $this->logger->info('Add ban ip: ' . $ip . ' to ' . $banned_to);
            } catch (Exeption $e) {
                $this->logger->error('Failed to create/update ip ban -> ' . $e->getMessage());
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        }

        return $this->response->success();
    }
}