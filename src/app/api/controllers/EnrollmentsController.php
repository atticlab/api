<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;
use App\Models\Companies;
use App\Models\Enrollments;
use App\Models\Agents;
use App\Models\RegUsers;
use Smartmoney\Stellar\Account;

class EnrollmentsController extends ControllerBase
{

    public function listAction()
    {

        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        //get list of enrollments
        $limit  = $this->request->get('limit')  ?? null;
        $offset = $this->request->get('offset') ?? null;

        $type = $this->request->get('type') ?? null;

        //if need filter by type
        if (!empty($type) ) {

            if (!in_array($type, Enrollments::$accepted_types)) {
                return $this->response->error(Response::ERR_BAD_PARAM, 'type');
            }

            try {
                $result = Enrollments::findWithIndex('type', $type, $limit, $offset);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }

            if ($type == 'agent') {
                //more data for agents records
                foreach ($result as $key => &$item) {
                    if (!Agents::isExist($item->target_id)) {
                        unset($result[$key]);
                    }
                    $agent_data = Agents::getDataByID($item->target_id);
                    if (!Companies::isExist($agent_data->cmp_code)) {
                        unset($result[$key]);
                    }
                    $cmp_data = Companies::getDataByID($agent_data->cmp_code);
                    $item->cmp_title   = $cmp_data->title;
                    $item->target_type = $agent_data->type;
                }
            }

            return $this->response->items($result);

        }

        //get all enrollments
        try {
            $result = Enrollments::find($limit, $offset);
            return $this->response->items($result);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function acceptAction($id)
    {

        if (empty($id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'enrollment_id');
        }

        $account_id = $this->payload->account_id   ?? null;
        $tx_trust   = $this->payload->tx_trust  ?? null;
        $login      = $this->payload->login     ?? null;
        $token      = $this->payload->token     ?? null;

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (empty($tx_trust)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'tx_trust');
        }

        if (empty($login)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'login');
        }

        if (empty($token)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'token');
        }

        try {
            $enrollment = Enrollments::findFirst($id);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        if (empty($enrollment) || empty($enrollment->otp) || $enrollment->otp != $token) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }

        $enrollment->stage      = Enrollments::STAGE_APPROVED;
        $enrollment->account_id = $account_id;
        $enrollment->tx_trust   = $tx_trust;
        $enrollment->login      = $login;

        try {

            if ($enrollment->update()) {
                return $this->response->single(['message' => 'success']);
            }

            $this->logger->emergency('Riak error while updating enrollment');
            throw new Exception(Exception::SERVICE_ERROR);

        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function declineAction($id)
    {

        if (empty($id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'enrollment_id');
        }

        $token = $this->payload->token ?? null;

        if (empty($token)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'token');
        }

        try {
            $enrollment = Enrollments::findFirst($id);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        if (empty($enrollment) || empty($enrollment->otp) || $enrollment->otp != $token) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }

        $enrollment->stage = Enrollments::STAGE_DECLINED;

        try {

            if ($enrollment->update()) {
                return $this->response->single(['message' => 'success']);
            }

            $this->logger->emergency('Riak error while updating enrollment');
            throw new Exception(Exception::SERVICE_ERROR);

        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function approveAction($id)
    {

        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        if (empty($id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'enrollment_id');
        }

        try {
            $enrollment = Enrollments::findFirst($id);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        if ($enrollment->stage != Enrollments::STAGE_APPROVED) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'enrollment_id');
        }

        if (empty($enrollment->type) || empty($enrollment->target_id) || !in_array($enrollment->type, Enrollments::$accepted_types)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'enrollment_id');
        }

        switch ($enrollment->type) {

            case Enrollments::TYPE_AGENT:

                try {
                    $agent = Agents::findFirst($enrollment->target_id);
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                $agent->account_id = $enrollment->account_id;
                $agent->login      = $enrollment->login;

                try {

                    if ($agent->update() && $enrollment->update()) {
                        return $this->response->single(['message' => 'success']);
                    }

                    $this->logger->emergency('Riak error while enrollment approve');
                    throw new Exception(Exception::SERVICE_ERROR);

                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                break;

            case Enrollments::TYPE_USER:

                try {
                    $user = RegUsers::findFirst($enrollment->target_id);
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                $user->account_id = $enrollment->account_id;
                $user->login      = $enrollment->login;

                try {

                    if ($user->update() && $enrollment->update()) {
                        return $this->response->single(['message' => 'success']);
                    }

                    $this->logger->emergency('Riak error while enrollment approve');
                    throw new Exception(Exception::SERVICE_ERROR);

                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                break;

        }


    }
}