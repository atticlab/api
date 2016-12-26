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
        try {
            $reguser = new RegUsers();
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $reguser->ipn_code_s    = $this->payload->ipn_code      ?? null;
        $reguser->passport_s    = $this->payload->passport      ?? null;
        $reguser->email_s       = $this->payload->email         ?? null;
        $reguser->phone_s       = $this->payload->phone         ?? null;
        $reguser->asset_s       = $this->payload->asset         ?? null;
        $reguser->surname_s     = $this->payload->surname       ?? null;
        $reguser->name_s        = $this->payload->name          ?? null;
        $reguser->middle_name_s = $this->payload->middle_name   ?? null;
        $reguser->address_s     = $this->payload->address       ?? null;

        try {
            if ($reguser->create()) {
                //create enrollment for reguser
                try {
                    $enrollment = new Enrollments();
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }
                $random = new \Phalcon\Security\Random;
                $enrollment->type_s         = Enrollments::TYPE_USER;
                $enrollment->target_id_s    = $reguser->id;
                $enrollment->stage_i        = Enrollments::STAGE_CREATED;
                $enrollment->asset_s        = $this->payload->asset ?? null;
                $enrollment->otp_s          = $random->base64Safe(8);
                $enrollment->expiration     = time() + 60 * 60 * 24;

                try {
                    if ($enrollment->create()) {
                        // Send email to registered user
                        $sent = $this->mailer->send($reguser->email_s, 'Welcome to smartmoney',
                            ['user_enrollment_created', ['password' => $enrollment->otp_s]]);
                        if (!$sent) {
                            $this->logger->emergency('Cannot send email with welcome code to registered user (' . $reguser->email_s . ')');
                        }

                        return $this->response->success();
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
        $limit  = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }
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
            $reguser = RegUsers::findFirstByField('ipn_code_s', $ipn_code);
        } elseif (!empty($passport)) {
            $reguser = RegUsers::findFirstByField('passport_s', $passport);
        } elseif (!empty($phone)) {
            $reguser = RegUsers::findFirstByField('phone_s', $phone);
        } elseif (!empty($email)) {
            $reguser = RegUsers::findFirstByField('email_s', $email);
        }

        if (empty($reguser)){
            return $this->response->error(Response::ERR_NOT_FOUND, 'registered_user');
        }

        return $this->response->single((array)$reguser);
    }
}