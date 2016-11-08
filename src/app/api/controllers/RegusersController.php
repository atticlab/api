<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;
use App\Models\Enrollments;
use App\Models\RegUsers;
use Smartmoney\Stellar\Account;

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

        // Create new reguser
        $ipn_code = $this->payload->ipn_code ?? null;
        $passport = $this->payload->passport   ?? null;
        $email    = $this->payload->email   ?? null;
        $phone    = $this->payload->phone   ?? null;

        try {
            $reguser = new RegUsers();
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $reguser->ipn_code = $ipn_code;
        $reguser->passport = $passport;
        $reguser->email    = $email;
        $reguser->phone    = $phone;

        $reguser->asset = $this->payload->asset   ?? null;
        $reguser->surname = $this->payload->surname ?? null;
        $reguser->name = $this->payload->name   ?? null;
        $reguser->middle_name = $this->payload->middle_name   ?? null;
        $reguser->address = $this->payload->address   ?? null;

        try {
            if ($reguser->create()) {

                //create enrollment for reguser

                try {
                    $enrollment = new Enrollments();
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                $random = new \Phalcon\Security\Random;

                $enrollment->type           = Enrollments::TYPE_USER;
                $enrollment->target_id      = $reguser->id;
                $enrollment->stage          = Enrollments::STAGE_CREATED;
                $enrollment->asset          = $this->payload->asset ?? null;
                $enrollment->otp            = $random->base64Safe(8);
                $enrollment->expiration     = time() + 60 * 60 * 24;

                try {

                    if ($enrollment->create()) {

                        // Send email to registered user
                        $sent = $this->mailer->send($reguser->email, 'Welcome to smartmoney',
                            ['user_enrollment_created', ['password' => $enrollment->otp]]);

                        if (!$sent) {
                            $this->logger->emergency('Cannot send email with welcome code to registered user (' . $reguser->email . ')');
                        }

                        return $this->response->single(['message' => 'success']);

                    }

                    $this->logger->emergency('Riak error while creating enrollment for reguser');
                    return $this->response->error(Response::ERR_SERVICE);

                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }
            }

            $this->logger->emergency('Riak error while creating reguser');
            return $this->response->error(Response::ERR_SERVICE);
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

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        //get list of RegUsers
        $limit = $this->request->get('limit')  ?? null;
        $offset = $this->request->get('offset') ?? null;

        try {
            $result = RegUsers::find($limit, $offset);
            return $this->response->items($result);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function getAction()
    {
        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $ipn_code = $this->request->get('ipn_code')  ?? null;
        $passport = $this->request->get('passport')  ?? null;
        $email    = $this->request->get('email')     ?? null;
        $phone    = $this->request->get('phone')     ?? null;

        if (empty($ipn_code) && empty($passport) && empty($phone) && empty($email)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'criteria');
        }

        if (!empty($ipn_code)) {
            $data = RegUsers::isExistByIndex('ipn_code', $ipn_code);
        } elseif (!empty($passport)) {
            $data = RegUsers::isExistByIndex('passport', $passport);
        } elseif (!empty($phone)) {
            $data = RegUsers::isExistByIndex('phone', $phone);
        } elseif (!empty($email)) {
            $data = RegUsers::isExistByIndex('email', $email);
        }

        if (empty($data)){
            return $this->response->error(Response::ERR_NOT_FOUND, 'registered_user');
        }

        $id = $data[0];

        $reguser = RegUsers::getDataByID($id);

        $data = [
            'id' => $reguser->id,
            'ipn_code' => $reguser->ipn_code,
            'asset' => $reguser->asset,
            'surname' => $reguser->surname,
            'name' => $reguser->name,
            'middle_name' => $reguser->middle_name,
            'email' => $reguser->email,
            'phone' => $reguser->phone,
            'address' => $reguser->address,
            'passport' => $reguser->passport
        ];

        return $this->response->single($data);
    }
}