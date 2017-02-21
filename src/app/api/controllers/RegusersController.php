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
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        // Create new reguser
        try {
            $reguser = new RegUsers();
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $reguser->ipn_code_s = $this->payload->ipn_code      ?? null;
        $reguser->passport_s = $this->payload->passport      ?? null;
        $reguser->email_s = $this->payload->email         ?? null;
        $reguser->phone_s = $this->payload->phone         ?? null;
        $reguser->asset_s = $this->payload->asset         ?? null;
        $reguser->surname_s = $this->payload->surname       ?? null;
        $reguser->name_s = $this->payload->name          ?? null;
        $reguser->middle_name_s = $this->payload->middle_name   ?? null;
        $reguser->address_s = $this->payload->address       ?? null;
        $reguser->created_i = time();

        try {
            if ($reguser->create()) {
                //create enrollment for reguser
                try {
                    $enrollment = new Enrollments();
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }
                $random = new \Phalcon\Security\Random;
                $enrollment->type_s = Enrollments::TYPE_USER;
                $enrollment->target_id_s = $reguser->id;
                $enrollment->stage_i = Enrollments::STAGE_CREATED;
                $enrollment->asset_s = $this->payload->asset ?? null;
                $enrollment->otp_s = $random->base64Safe(8);
                $enrollment->created_i = time();
                $enrollment->expiration = $enrollment->created_i + 60 * 60 * 24;

                try {
                    if ($enrollment->create()) {
                        // Send email to registered user
                        $sent = $this->mailer->send($reguser->email_s, 'Welcome to smartmoney',
                            ['user_enrollment_created', ['password' => $enrollment->otp_s]]);
                        if (!$sent) {
                            $this->logger->emergency('Cannot send email with welcome code to registered user (' . $reguser->email_s . ')');
                        }

                        return $this->response->json((array)Enrollments::clearYzSuffixes($enrollment));
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
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $limit = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }

        $ipn_code = $this->request->get('ipn_code') ?? null;
        $passport = $this->request->get('passport') ?? null;
        $email = $this->request->get('email') ?? null;
        $phone = $this->request->get('phone') ?? null;

        if (!empty($ipn_code)) {
            try {
                $result = RegUsers::findWithField('ipn_code_s', $ipn_code, $limit, $offset, 'created_i', 'desc', false);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        } elseif (!empty($passport)) {
            try {
                $result = RegUsers::findWithField('passport_s', $passport, $limit, $offset, 'created_i', 'desc', false);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        } elseif (!empty($email)) {
            try {
                $result = RegUsers::findWithField('email_s', $email, $limit, $offset, 'created_i', 'desc', false);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        } elseif (!empty($phone)) {
            try {
                $result = RegUsers::findWithField('phone_s', $phone, $limit, $offset, 'created_i', 'desc', false);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        } else {
            try {
                $result = RegUsers::find($limit, $offset, 'created_i', 'desc');
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        }

        return $this->response->json($result);
    }

}