<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;
use App\Models\RegUsers;
use Smartmoney\Stellar\Account;

class RegusersController extends ControllerBase
{
    public function createAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        // Create new reguser
        $ipn_code = $this->payload->ipn_code ?? null;

        if (empty($ipn_code)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'ipn_code');
        }

        if (RegUsers::isExist($ipn_code)) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'ipn_code');
        }

        try {
            $reguser = new RegUsers($ipn_code);
        } catch (Exception $e) {

            $this->handleException($e->getCode(), $e->getMessage());
        }

        $reguser->asset = $this->payload->asset   ?? null;
        $reguser->surname = $this->payload->surname ?? null;
        $reguser->name = $this->payload->name   ?? null;
        $reguser->middle_name = $this->payload->middle_name   ?? null;
        $reguser->email = $this->payload->email   ?? null;
        $reguser->phone = $this->payload->phone   ?? null;
        $reguser->address = $this->payload->address   ?? null;
        $reguser->passport = $this->payload->passport   ?? null;
        $reguser->phone = $this->payload->phone   ?? null;

        try {
            if ($reguser->create()) {
                return $this->response->single(['message' => 'success']);
            }

            $this->logger->emergency('Riak error while creating company');
            return $this->response->error(Response::SERVICE_ERROR);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function listAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        //get list of RegUsers
        $limit = $this->request->get('limit')  ?? null;
        $offset = $this->request->get('offset') ?? null;

        try {
            $result = RegUsers::find($limit, $offset);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }
        return $this->response->items($result);
    }

    public function getAction($code)
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        if (empty($code)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'code');
        }

        if (!RegUsers::isExist($code)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'company');
        }

        $reguser = RegUsers::getDataByID($code);

        $data = [
            'ipn_code' => $reguser->ipn_code,
            'asset' => $reguser->asset,
            'surname' => $reguser->surname,
            'name' => $reguser->name,
            'middle_name' => $reguser->middle_name,
            'email' => $reguser->email,
            'phone' => $reguser->phone,
            'address' => $reguser->address,
            'passport' => $reguser->passport,
            'phone' => $reguser->phone
        ];

        return $this->response->single($data);
    }
}