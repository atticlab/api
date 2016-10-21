<?php

namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;

class ControllerBase extends \Phalcon\Mvc\Controller
{
    protected $payload;

    public function beforeExecuteRoute($dispatcher)
    {

        $this->payload = json_decode(file_get_contents('php://input'));

        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');

        if ($this->request->isOptions()) {
            $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-CSRF-Token, X-Requested-With, X-HTTP-Method-Override, Content-Range, Content-Disposition, Content-Type, Authorization, Signed-Nonce');
            $this->response->setHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, DELETE');
            $this->response->sendHeaders();
            exit;
        }

        if ($dispatcher->getControllerName() != 'nonce' && !$this->request->checkSignature()) {
            return $this->response->error(Response::ERR_BAD_SIGN);
        }

        /*
           //error handler example

           try{
               throw new \App\Lib\Exception(\App\Lib\Exception::BAD_PARAM);
               throw new \App\Lib\Exception(\App\Lib\Exception::BAD_PARAM, 'email');
           } catch (Exception $e) {
               var_dump($e->getMessage());exit;
               switch ($e->getCode()) {
                   case \App\Lib\Exception::BAD_PARAM:
                       return $this->response->error(\App\Lib\Response::ERR_BAD_PARAM, $e->getMessage());
                       break;

                   case \App\Lib\Exception::EMPTY_PARAM:
                       $this->logger->emergency('Lost riak connection!!');
                       return $this->response->error(\App\Lib\Response::ERR_EMPTY_PARAM, $e->getMessage());
                       break;

               }
           }
        */

    }
}