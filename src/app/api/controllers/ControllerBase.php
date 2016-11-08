<?php

namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;
use Smartmoney\Stellar\Helpers;

abstract class ControllerBase extends \Phalcon\Mvc\Controller
{
    protected $payload;

    /**
     * Calls every time, when phalcon's try to execute route for check login credentials
     * @param $dispatcher
     * @return Response error
     */
    public function beforeExecuteRoute($dispatcher)
    {
        $this->payload = json_decode(file_get_contents('php://input'));
        if (empty($this->payload)) {
            $this->payload = (object)$this->request->getPost();
        }
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');
        if ($this->request->isOptions()) {
            $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-CSRF-Token, X-Requested-With, X-HTTP-Method-Override, Content-Range, Content-Disposition, Content-Type, Authorization, Signed-Nonce');
            $this->response->setHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, DELETE');
            $this->response->sendHeaders();
            exit;
        }



        if ($dispatcher->getControllerName() != 'nonce') {

            $allow_routes = [
                'enrollments/accept',
                'enrollments/decline'
            ];

            $current_route = $dispatcher->getControllerName() . '/' . $dispatcher->getActionName();

            if (!in_array($current_route, $allow_routes) && !$this->request->checkSignature()) {
                return $this->response->error(Response::ERR_BAD_SIGN);
            }

        }
    }

    /**
     * Hadle an exception
     * @param $code
     * @param $msg
     * @return Response error
     */
    protected function handleException($code, $msg){
        switch ($code) {
            case Exception::BAD_PARAM:
                return $this->response->error(Response::ERR_BAD_PARAM, $msg);
                break;

            case Exception::EMPTY_PARAM:
                return $this->response->error(Response::ERR_EMPTY_PARAM, $msg);
                break;

            case Exception::SERVICE_ERROR:
                return $this->response->error(Response::ERR_SERVICE, $msg);
                break;

            case Exception::NOT_FOUND:
                return $this->response->error(Response::ERR_NOT_FOUND, $msg);
                break;

            case Exception::ALREADY_EXIST:
                return $this->response->error(Response::ERR_ALREADY_EXISTS, $msg);
                break;

            case Exception::UNKNOWN:
                return $this->response->error(Response::ERR_UNKNOWN, $msg);
                break;

            default:
                $this->logger->emergency('Unknown error with code ' . $code . ' and message ' . $msg);
                return $this->response->error(Response::ERR_UNKNOWN);
        }
    }

    /**
     * returns true if this accountId is allowed
     * @param $accountId -- logined user's account Id
     * @param array $allowed_types -- array of needle allowed user types
     * @return bool
     */
    protected function isAllowedType($accountId, array $allowed_types) {
        if (in_array(Account::TYPE_ADMIN, $allowed_types)){
            //admin is allowed, need to check is account admin
            if ($this->isAdmin($accountId)) {
                return true;
            }
        }
        return in_array(Account::getAccountType($accountId, $this->config->horizon->host, $this->config->horizon->port), $allowed_types);
    }

    /**
     * Check's if accountId has admin type
     * @param $accountId
     * @return bool
     * @throws \Exception
     */
    private function isAdmin($accountId) {
        $master_info = Helpers::masterAccountInfo($this->config->master_key, $this->config->horizon->host, $this->config->horizon->port);
        return in_array($accountId, Helpers::getAdminsList($master_info, $this->config->weights->admin));
    }
}