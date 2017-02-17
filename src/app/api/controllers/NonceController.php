<?php
namespace App\Controllers;

use App\Lib\Errors;
use Smartmoney\Stellar\Account;
use App\Lib\Response;

class NonceController extends ControllerBase
{

    public function indexAction()
    {
        $accountId = $this->request->get('accountId');
        if (empty($accountId)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'accountId');
        }
        if (!Account::isValidAccountId($accountId)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'accountId');
        }
        $this->request->setAccountId($accountId);

        return $this->response->json();
    }
}