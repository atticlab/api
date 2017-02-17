<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;
use App\Models\Companies;
use Smartmoney\Stellar\Account;

class CompaniesController extends ControllerBase
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
        // Create new company
        $code = $this->payload->code ?? null;
        if (empty($code)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'code');
        }
        if (Companies::isExist($code)) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'code');
        }
        try {
            $company = new Companies($code);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $company->title_s = $this->payload->title   ?? null;
        $company->address_s = $this->payload->address ?? null;
        $company->email_s = $this->payload->email   ?? null;
        $company->phone_s = $this->payload->phone   ?? null;
        $company->created_date_i = time();

        try {
            if ($company->create()) {
                return $this->response->json();
            }
            $this->logger->emergency('Riak error while creating company');
            throw new Exception(Exception::SERVICE_ERROR);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
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
        $limit = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }
        try {
            $result = Companies::find($limit, $offset, 'created_date_i', 'desc');
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        return $this->response->json($result);
    }

    public function getAction($code)
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        if (empty($code)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'code');
        }
        if (!Companies::isExist($code)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'company');
        }
        $company = Companies::getDataByID($code);

        return $this->response->json((array)$company);
    }
}