<?php
namespace App\Controllers;

use App\Lib\Errors;
use App\Lib\Response;

class NonceController extends ControllerBase
{

    public function indexAction()
    {
        return $this->response->single();
    }

}