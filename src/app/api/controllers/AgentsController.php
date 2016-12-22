<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Lib\Exception;

use App\Models\Agents;
use App\Models\Companies;
use App\Models\Enrollments;

use App\Services\Helpers;
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
        $agent->type_i     = $type;
        $agent->asset_s    = $asset;
        $agent->cmp_code_s = $cmp_code;
        $agent->created_i  = time();
        try {
            if ($agent->create()) {
                //create enrollment for agent
                try {
                    $enrollment = new Enrollments();
                } catch (Exception $e) {
                    return $this->handleException($e->getCode(), $e->getMessage());
                }
                $enrollment->type_s           = Enrollments::TYPE_AGENT;
                $enrollment->target_id_s      = $agent->id;
                $enrollment->stage_i          = Enrollments::STAGE_CREATED;
                $enrollment->asset_s          = $asset;
                $enrollment->otp_s            = Enrollments::generateOTP();
                $enrollment->expiration       = time() + 60 * 60 * 24;

                try {
                    if ($enrollment->create()) {
                        //get company email for send enrollment
                        $company = Companies::getDataByID($cmp_code);
                        if (empty($company->email_s)) {
                            $this->logger->emergency('Cannot get email of company (company code: ' . $cmp_code . ')');
                        } else {
                            // Send email to registered user
                            $sent = $this->mailer->send($company->email_s, 'Welcome to smartmoney',
                                ['enrollment_created', ['password' => $enrollment->otp_s]]);
                            if (!$sent) {
                                $this->logger->emergency('Cannot send email with welcome code to company (' . $company->email_s . ')');
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
        $allowed_types = [
            Account::TYPE_ADMIN
        ];
        $requester = $this->request->getAccountId();
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $limit  = $this->request->get('limit')  ?? $this->config->riak->default_limit;
        $offset = $this->request->get('offset') ?? 0;

        $company_code   = $this->request->get('company_code') ?? null;
        $type           = $this->request->get('type') ?? null;

        if (!empty($company_code)) {
            try {
                $result = Agents::findWithField('cmp_code_s', $company_code, $limit, $offset);
            } catch (Exception $e) {
                return $this->handleException($e->getCode(), $e->getMessage());
            }
        } elseif (!empty($type)) {
            try {
                $result = Agents::findWithField('type_i', $type, $limit, $offset);
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
            if (!Companies::isExist($item->cmp_code_s)) {
                unset($result[$key]);
            }
            $cmp_data = Companies::getDataByID($item->cmp_code_s);
            $item->cmp_title = $cmp_data->title_s;
        }

        return $this->response->items(Helpers::clearYzSuffixes($result));

    }

}