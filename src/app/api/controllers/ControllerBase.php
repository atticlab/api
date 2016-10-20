<?php

namespace App\Controllers;

class ControllerBase extends \Phalcon\Mvc\Controller
{
    protected $payload;

    public function beforeExecuteRoute()
    {
        $this->payload = json_decode(file_get_contents('php://input'));

        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Credentials', 'true');

        if ($this->request->isOptions()) {
            $this->response->setHeader('Access-Control-Allow-Headers', 'Origin, X-CSRF-Token, X-Requested-With, X-HTTP-Method-Override, Content-Range, Content-Disposition, Content-Type, Authorization');
            $this->response->setHeader('Access-Control-Allow-Methods', 'OPTIONS, GET, POST, PUT, DELETE');
            $this->response->sendHeaders();
            exit;
        }

        $this->request->checkSignature();
    }
}