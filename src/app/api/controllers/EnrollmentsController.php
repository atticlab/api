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
        $type = $this->request->get('type') ?? null;
        //if need filter by type
        if (!empty($type)) {
            if (!in_array($type, Enrollments::$accepted_types)) {
                return $this->response->error(Response::ERR_BAD_PARAM, 'type');
            }
            try {
                $result = Enrollments::findWithField('type_s', $type, $limit, $offset, 'created_i', 'desc');
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
            if ($type == 'agent') {
                //more data for agents enrollments
                foreach ($result as $key => &$item) {
                    if (!Agents::isExist($item->target_id)) {
                        unset($result[$key]);
                    }
                    $agent_data = Agents::getDataByID($item->target_id);
                    if (!Companies::isExist($agent_data->cmp_code)) {
                        unset($result[$key]);
                    }
                    $item->company_data = Companies::getDataByID($agent_data->cmp_code);
                    $item->agent_data = $agent_data;
                }
            } else {
                //more data for users enrollments
                foreach ($result as $key => &$item) {
                    if (!RegUsers::isExist($item->target_id)) {
                        unset($result[$key]);
                    }
                    $item->user_data = RegUsers::getDataByID($item->target_id);
                }
            }

            return $this->response->json(array_values($result));
        }

        //get all enrollments
        try {
            $result = Enrollments::find($limit, $offset, 'created_i', 'desc');

            return $this->response->json($result);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function getUserEnrollmentAction($otp)
    {
        if (empty($otp)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'token');
        }
        $enrollment = Enrollments::findFirstByField('otp_s', $otp);
        if (!$enrollment) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        if (empty($enrollment) || empty($enrollment->target_id) || empty($enrollment->type) || $enrollment->type != 'user') {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        if ($enrollment->stage != Enrollments::STAGE_CREATED) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        if (!RegUsers::isExist($enrollment->target_id)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'registered_user');
        }
        $user_data = RegUsers::getDataByID($enrollment->target_id);
        if (!$user_data) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'registered_user');
        }
        $enrollment->user_data = $user_data;

        return $this->response->json((array)$enrollment);
    }

    public function getAgentEnrollmentAction($otp)
    {
        if (empty($otp)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'token');
        }
        if (empty($this->request->get('company_code'))) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'company_code');
        }
        $company_code = $this->request->get('company_code');
        $enrollment = Enrollments::findFirstByField('otp_s', $otp);
        if (!$enrollment) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        if (empty($enrollment) || empty($enrollment->target_id) || empty($enrollment->type) || $enrollment->type != 'agent') {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        if ($enrollment->stage != Enrollments::STAGE_CREATED) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        if (!Agents::isExist($enrollment->target_id)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'agent');
        }
        $agent_data = Agents::getDataByID($enrollment->target_id);
        if (!$agent_data || empty($agent_data->cmp_code) || $agent_data->cmp_code != $company_code) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'agent');
        }
        $company_code = $agent_data->cmp_code;
        $enrollment->agent_data = $agent_data;
        $cmp_data = Companies::getDataByID($company_code);
        if (!$cmp_data) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'company');
        }
        $enrollment->company_data = $cmp_data;

        return $this->response->json((array)$enrollment);
    }

    public function acceptAction($id)
    {
        if (empty($id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'enrollment_id');
        }
        $account_id = $this->payload->account_id   ?? null;
        $tx_trust = $this->payload->tx_trust  ?? null;
        $login = $this->payload->login     ?? null;
        $token = $this->payload->token     ?? null;
        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }
        if (empty($tx_trust)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'tx_trust');
        }
        if (empty($token)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'token');
        }
        try {
            $enrollment = Enrollments::findFirst($id);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
        if (empty($enrollment) || empty($enrollment->otp_s) || $enrollment->otp_s != $token) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        $enrollment->stage_i = Enrollments::STAGE_APPROVED;
        $enrollment->account_id_s = $account_id;
        $enrollment->tx_trust = $tx_trust;
        $enrollment->login_s = $login;
        try {
            if ($enrollment->update()) {
                return $this->response->json([], false);
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
        if (empty($enrollment) || empty($enrollment->otp_s) || $enrollment->otp_s != $token) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'enrollment');
        }
        $enrollment->stage_i = Enrollments::STAGE_DECLINED;
        try {
            if ($enrollment->update()) {
                return $this->response->json([], false);
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
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
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
        if ($enrollment->stage_i != Enrollments::STAGE_APPROVED) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'enrollment_id');
        }
        if (empty($enrollment->type_s) || empty($enrollment->target_id_s) || !in_array($enrollment->type_s,
                Enrollments::$accepted_types)
        ) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'enrollment_id');
        }

        switch ($enrollment->type_s) {

            case Enrollments::TYPE_AGENT:
                try {
                    $agent = Agents::findFirst($enrollment->target_id_s);
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }
                $agent->account_id_s = $enrollment->account_id_s;
                $agent->login_s = $enrollment->login_s;
                try {
                    if ($agent->update() && $enrollment->update()) {
                        return $this->response->json();
                    }
                    $this->logger->emergency('Riak error while enrollment approve');
                    throw new Exception(Exception::SERVICE_ERROR);
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                break;

            case Enrollments::TYPE_USER:

                try {
                    $user = RegUsers::findFirst($enrollment->target_id_s);
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }
                $user->account_id_s = $enrollment->account_id_s;
                $user->login_s = $enrollment->login_s;
                try {
                    if ($user->update() && $enrollment->update()) {
                        return $this->response->json();
                    }
                    $this->logger->emergency('Riak error while enrollment approve');
                    throw new Exception(Exception::SERVICE_ERROR);
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                break;

            default:

                return $this->response->error(Response::ERR_BAD_PARAM, 'enrollment_type');
        }
    }
}