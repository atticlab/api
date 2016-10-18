<?php
namespace App\Controllers;

use App\Lib\Errors;

class IndexController extends ControllerBase
{
    public function indexAction()
    {
        return $this->response->setJsonContent(['Crypto API']);
    }

    public function notFoundAction()
    {
        return Errors::returnJson(Errors::NOT_FOUND);
    }
}