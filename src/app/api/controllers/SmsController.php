<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Sms;
use App\Lib\Exception;
use DateTime;
use Smartmoney\Stellar\Account;
use SoapClient;

class SmsController extends ControllerBase
{

    public function getAction($account_id)
    {
        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }
        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }

        $sms = Sms::findFirst($account_id);
        if (empty($sms)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'sms');
        }

        $result = [];
        $result['account_id'] = $sms->account_id;
        $result['is_confirmed'] = $sms->is_confirmed_b;
        $result['phone'] = $sms->phone_s;

        return $this->response->json($sms);
    }

    public function listByPhoneAction($phone)
    {
        $limit = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }
        //get all accounts for phone
        try {
            if (empty($phone)) {
                throw new Exception(Exception::EMPTY_PARAM, 'phone');
            }
            $data = Sms::findWithField('phone_s', $phone, $limit, $offset);
            $result = [];
            if ($data) {
                $i = 0;
                foreach ($data as $sms) {
                    $result[++$i] = [];
                    $result[$i]['account_id'] = $sms->account_id;
                    $result[$i]['is_confirmed'] = $sms->is_confirmed;
                    $result[$i]['phone'] = $sms->phone;
                }
            }

            return $this->response->json($result);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function createSMSAction()
    {
        $account_id = $this->payload->account_id   ?? null;
        $phone = $this->payload->phone   ?? null;

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }

        if (empty($phone)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'phone');
        }

        try {
            if (Sms::findFirst($account_id)) {
                return $this->response->error(Response::ERR_ALREADY_TAKEN, 'account_id');
            }
        } catch (Exception $e) {
            //Do nothing if ($e->getCode() === Exception::NOT_FOUND)
        }

        if (Sms::getPhoneAccountsCount($phone) >= $this->config->sms->max_phone_accounts) {
            return $this->response->error(Response::ERR_TOO_MANY_ACCS, 'phone');
        }

        //OTP
        $otp = substr(str_shuffle('0123456789'), 1, $this->config->sms->otp_length);

        $sms = new Sms($account_id);
        $sms->account_id = $account_id;
        $sms->phone_s = $phone;
        $sms->otp_s = $otp;
        $sms->is_confirmed_b = false;

        try {
            $sendResult = $this->sendSMS($sms);

            if (isset($sendResult->SendSMSResult->ResultArray[1])) {
                $sms->sms_id_s = $sendResult->SendSMSResult->ResultArray[1];
                $sms->send_timestamp_i = (new DateTime())->getTimestamp();
            } else {
                throw new Exception(Exception::ERR_SMS_SEND);
            }
            if (!$sms->create()) {
                $this->logger->emergency('Riak error while creating sms');
                throw new Exception(Exception::SERVICE_ERROR);
            }
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
        $result = [];
        $result['account_id'] = $sms->account_id;
        $result['is_confirmed'] = $sms->is_confirmed_b;
        $result['phone'] = $sms->phone_s;

        return $this->response->json($result);
    }

    public function resendAction()
    {
        $account_id = $this->payload->account_id   ?? null;
        $phone = $this->payload->phone   ?? null;

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }

        if (empty($phone)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'phone');
        }

        $sms = Sms::findFirst($account_id);

        if ($sms->phone_s != $phone) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'phone');
        }

        $sms->otp_s = substr(str_shuffle('0123456789'), 1, 6);
        $sms->is_confirmed_b = false;

        try {
            $sendResult = $this->sendSMS($sms);
            if (isset($sendResult->SendSMSResult->ResultArray[1])) {
                $sms->sms_id_s = $sendResult->SendSMSResult->ResultArray[1];
                $sms->send_timestamp_i = (new DateTime())->getTimestamp();
            } else {
                throw new Exception(Exception::ERR_SMS_SEND);
            }
            if (!$sms->update()) {
                $this->logger->emergency('Riak error while updating sms');
                throw new Exception(Exception::SERVICE_ERROR);
            }
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
        $result = [];
        $result['account_id'] = $sms->account_id;
        $result['is_confirmed'] = $sms->is_confirmed_b;
        $result['phone'] = $sms->phone_s;

        return $this->response->json($result);
    }

    public function submitOTPAction()
    {
        $account_id = $this->payload->account_id   ?? null;
        $phone = $this->payload->phone   ?? null;
        $otp = $this->payload->otp   ?? null;

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }

        if (empty($phone)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'phone');
        }

        if (empty($otp)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'otp');
        }

        $sms = Sms::findFirst($account_id);

        if ($sms->phone_s != $phone) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'phone');
        }

        if ($sms->otp_s != $otp) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'otp');
        }

        $cur_timestamp = (new DateTime())->getTimestamp();

        if (($cur_timestamp - $sms->send_timestamp_i) > $this->config->sms->otp_ttl) {
            return $this->response->error(Response::ERR_OTP_TTL, 'otp');
        }

        $sms->is_confirmed_b = true;

        try {
            if (!$sms->update()) {
                $this->logger->emergency('Riak error while updating sms');
                throw new Exception(Exception::SERVICE_ERROR);
            }
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
        $result = [];
        $result['account_id'] = $sms->account_id;
        $result['is_confirmed'] = $sms->is_confirmed_b;
        $result['phone'] = $sms->phone_s;

        return $this->response->json($result);
    }

    public function checkAction()
    {
        $account_id = $this->payload->account_id   ?? null;
        $phone = $this->payload->phone   ?? null;

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }

        if (empty($phone)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'phone');
        }

        $data = [];
        $data["is_confirmed"] = Sms::checkIsConfirmed($phone, $account_id);

        return $this->response->json($data);
    }

    private function sendSMS($sms)
    {

        $client = new SoapClient($this->config->sms->url);

        $authResult = $client->Auth($this->config->sms->auth);
        $balance = $client->GetCreditBalance();

        //sometimes it returns an object, fix for it
        if (gettype($balance) == "object") {
            $balance = intval($balance->GetCreditBalanceResult);
        }

        if ($balance < 1) {
            throw new Exception(Exception::ERR_SMS_NO_FUNDS);
        }

        //add phone format for send
        $phone = $sms->phone_s;
        if (substr($sms->phone_s, 0, strlen($this->config->sms->phone_prefix)) !== $this->config->sms->phone_prefix) {
            $phone = $this->config->sms->phone_prefix . $sms->phone_s;
        }

        $sms = [
            'sender'      => $this->config->sms->sender,
            'destination' => $phone,
            'text'        => $this->config->sms->msg . $sms->otp_s
        ];

        return $client->SendSMS($sms);
    }

}