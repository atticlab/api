<?php

namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;
use App\Models\Admins;
use Smartmoney\Stellar\Account;

class AdminsController extends ControllerBase
{
    public function createAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();

        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        //create new admin
        $account_id = $this->payload->account_id ?? null;

        if (Admins::isExist($account_id)) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'account_id');
        }

        try {
            $admin = new Admins($account_id);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $admin->name_s = $this->payload->name             ?? null;
        $admin->position_s = $this->payload->position     ?? null;
        $admin->comment = $this->payload->comment         ?? null;

        try {
            if ($admin->create()) {
                return $this->response->json();
            }
            $this->logger->emergency('Riak error while creating admin');
            throw new Exception(Exception::SERVICE_ERROR);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function getAction($account_id)
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (!Admins::isExist($account_id)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'admin');
        }

        $admin = Admins::getDataByID($account_id);

        if (empty($admin)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'admin');
        }

        return $this->response->json((array)$admin);
    }

    public function listAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $account_ids = $this->request->get('account_ids');

        if (empty($account_ids)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_ids');
        }

        if (!is_array($account_ids)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_ids');
        }

        $result = [];
        foreach ($account_ids as $key => $account_id) {
            try {
                $data = Admins::getDataByID($account_id);
                if (!empty($data)) {
                    $result[] = $data;
                }
            } catch (\Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        }

        return $this->response->json($result);
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

        $account_id = $this->payload->account_id;

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }

        try {
            $admin = Admins::findFirst($account_id);
            $admin->delete();
            $this->logger->info('Admin ' . $account_id . ' removed');

            return $this->response->json();
        } catch (\Exception $e) {
            $this->logger->error("Error removing admin: " . $account_id);

            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }
}