<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\RegUsers;

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


    }

    public function getAction()
    {
        //registered user or list of registered users
        $reguser = new RegUsers(123333);
        return $this->response->single(['obj' => $reguser]);
    }

    public function enrollmentsCreateAction()
    {
        //create enrollment for registered user
    }

    public function enrollmentsListAction()
    {
        //list of enrollments
    }

    public function enrollmentsApproveAction()
    {
        //approve enrollment
    }
}