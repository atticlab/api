<?php
namespace App\Controllers;

use App\Lib\Exception;
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
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $limit = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }
        //get all bans
        $bans = IpBans::find($limit, $offset);

        return $this->response->json($bans);
    }

    public function createAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $ip = $this->payload->ip ?? null;
        if (empty($ip)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'ip');
        }

        $ttl = $this->payload->ttl ?? null;
        if (empty($ttl)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'ttl');
        }

        $int_ip = ip2long($ip);
        $banned_to = time() + $ttl;
        try {
            $ip_ban = IpBans::getIpData($int_ip);
        } catch (Exeption $e) {
            $this->logger->error('Failed to get/create ban record -> ' . $e->getMessage());

            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $ip_ban->banned_to = $banned_to;
        try {
            $ip_ban->update();

            return $this->response->json();
        } catch (Exeption $e) {
            $this->logger->error('Failed to create/update ip ban -> ' . $e->getMessage());

            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function deleteAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $ip = $this->payload->ip ?? null;
        if (empty($ip)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'ip');
        }
        $int_ip = ip2long($ip);

        try {
            $ban = IpBans::findFirst($int_ip);
            $ban->delete();
        } catch (Exception $e) {
            $this->logger->notice('Trying to unban ip, that was not banned');
        }

        return $this->response->json();
    }
}