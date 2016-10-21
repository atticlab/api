<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Companies;

class CompaniesController extends ControllerBase
{

    public function createAction()
    {

        // Create new company

        $code = $this->request->getPost('code');

        if (empty($code)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'code');
        }

        if (Companies::isExist($this->riak, $code)) {
            return $this->response->error(Response::ERR_ALREADY_EXIST);
        }

        $company = new Companies($this->riak, $code);
        $company->title     = $this->request->getPost('title');
        $company->address   = $this->request->getPost('address');
        $company->email     = $this->request->getPost('email');
        $company->phone     = $this->request->getPost('phone');

        try {

            if ($company->create()) {
                return $this->response->single([
                    'message' => 'success'
                ]);
            } else {
                return $this->response->error(Response::ERR_UNKNOWN);
            }

        } catch (\Exception $e) {

            $msg  = Response::ERR_UNKNOWN;
            $code = Response::ERR_UNKNOWN;

            if (!empty($e->getMessage())) {
                $msg  = $e->getMessage();
                $code = $e->getCode();
            }

            return $this->response->error($code, $msg);
        }

    }
    public function getAction()
    {

        //company or list of companies
        $limit = null;
        $code  = null;

        $code  = $this->request->get('code');

        if (empty($code)) {
            $limit = $this->request->get('limit');
        }

        $result = Companies::getList($this->riak, $code, $limit);

        if (empty($result)) {
            return $this->response->error(Response::ERR_NOT_FOUND);
        }

        return $this->response->items($result);

    }
}