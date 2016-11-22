<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;

use App\Models\Agents;
use App\Models\Companies;
use App\Models\Enrollments;

use Smartmoney\Stellar\Account;

class AgentsController extends ControllerBase
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

        // Create new agent
        $type     = $this->payload->type            ?? null;
        $asset    = $this->payload->asset           ?? null;
        $cmp_code = $this->payload->company_code    ?? null;

        try {
            $agent = new Agents();
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $agent->type        = $type;
        $agent->asset       = $asset;
        $agent->cmp_code    = $cmp_code;

        $agent->created     = time();

        try {
            if ($agent->create()) {

                //create enrollment for agent

                try {
                    $enrollment = new Enrollments();
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }

                $enrollment->type           = Enrollments::TYPE_AGENT;
                $enrollment->target_id      = $agent->id;
                $enrollment->stage          = Enrollments::STAGE_CREATED;
                $enrollment->asset          = $asset;
                $enrollment->otp            = Enrollments::generateOTP();
                $enrollment->expiration     = time() + 60 * 60 * 24;

                try {

                    if ($enrollment->create()) {

                        //get company email for send enrollment

                        $company = Companies::getDataByID($cmp_code);

                        if (empty($company->email)){
                            $this->logger->emergency('Cannot get email of company (company code: ' . $cmp_code . ')');
                        } else {
                            // Send email to registered user
                            $sent = $this->mailer->send($company->email, 'Welcome to smartmoney',
                                ['enrollment_created', ['password' => $enrollment->otp]]);

                            if (!$sent) {
                                $this->logger->emergency('Cannot send email with welcome code to company (' . $company->email . ')');
                            }
                        }

                        return $this->response->success();

                    }

                    $this->logger->emergency('Riak error while creating enrollment for agent');
                    return $this->response->error(Response::ERR_SERVICE);

                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }
            }

            $this->logger->emergency('Riak error while creating agent');
            return $this->response->error(Response::ERR_SERVICE);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function listAction()
    {
        //list of agents
        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $limit  = $this->request->get('limit')  ?? null;
        $offset = $this->request->get('offset') ?? null;

        $company_code   = $this->request->get('company_code') ?? null;
        $type           = $this->request->get('type') ?? null;

        $result = [];

        if (!empty($company_code)) {
            try {
                $result = Agents::findWithIndex('cmp_code', $company_code, $limit, $offset);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        } elseif (!empty($type)) {
            try {
                $result = Agents::findWithIndex('type', $type, $limit, $offset);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        } else {
            try {
                $result = Agents::find($limit, $offset);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        }

        foreach ($result as $key => &$item) {
            if (!Companies::isExist($item->cmp_code)) {
                unset($result[$key]);
            }
            $cmp_data = Companies::getDataByID($item->cmp_code);
            $item->cmp_title = $cmp_data->title;
        }

        return $this->response->items($result);

    }

}