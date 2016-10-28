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

        if (!$this->isAllowedType($requester, $allowed_types)) {
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
            $this->handleException($e->getCode(), $e->getMessage());
        }

        $company->title   = $this->payload->title   ?? null;
        $company->address = $this->payload->address ?? null;
        $company->email   = $this->payload->email   ?? null;
        $company->phone   = $this->payload->phone   ?? null;

        try {

            if ($company->create()) {
                return $this->response->single(['message' => 'success']);
            }

            $this->logger->emergency('Riak error while creating company');
            throw new Exception(Exception::SERVICE_ERROR);

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

        //get list of companies
        $limit  = $this->request->get('limit')  ?? null;
        $offset = $this->request->get('offset') ?? null;

        try {
            $result = Companies::find($limit, $offset);
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

        if (!Companies::isExist($code)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'company');
        }

        $cmp_test = Companies::findFirst($code);

        $company = Companies::getDataByID($code);

        $data = [
            'code'      => $company->code,
            'title'     => $company->title,
            'address'   => $company->address,
            'phone'     => $company->phone,
            'email'     => $company->email
        ];

        return $this->response->single($data);



    }
}