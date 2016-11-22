<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\InvoicesBans;
use App\Models\Invoices;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;

class InvoicesController extends ControllerBase
{

    public function createAction()
    {

        $allowed_types = [
            Account::TYPE_NOT_CREATED,
            Account::TYPE_ANONYMOUS,
            Account::TYPE_REGISTERED,
            Account::TYPE_MERCHANT,
            Account::TYPE_EXCHANGE,
            Account::TYPE_SETTLEMENT,
            Account::TYPE_BANK
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $ban_data = InvoicesBans::getDataByID($requester);

        if (!empty($ban_data) && !empty($ban_data->blocked)  && $ban_data->blocked > time()) {
            return $this->response->error(Response::ERR_ACC_BLOCKED);
        }

        $invoice = new Invoices();

        $invoice->expires           = time() + $this->config->invoice->expired;
        $invoice->created           = time();
        $invoice->requested         = false;
        $invoice->is_in_statistic   = false;
        $invoice->payer             = false;

        $invoice->amount  = $this->payload->amount ?? null;
        $invoice->asset   = $this->payload->asset  ?? null;
        $invoice->account = $requester;

        $invoice->memo    = $this->payload->memo ?? null;

        try {

            if ($invoice->create()) {
                return $this->response->single(['id' => $invoice->id]);
            }

            $this->logger->emergency('Riak error while creating invoice');
            throw new Exception(Exception::SERVICE_ERROR);

        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function getAction($id)
    {

        $allowed_types = [
            Account::TYPE_ANONYMOUS,
            Account::TYPE_REGISTERED,
            Account::TYPE_MERCHANT,
            Account::TYPE_EXCHANGE,
            Account::TYPE_DISTRIBUTION,
            Account::TYPE_BANK,
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)){
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $ban_data = InvoicesBans::getDataByID($requester);

        if (!empty($ban_data) && !empty($ban_data->blocked) && $ban_data->blocked > time()) {
            return $this->response->error(Response::ERR_ACC_BLOCKED);
        }

        if (empty($id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'id');
        }

        if (mb_strlen($id) < Invoices::UUID_LENGTH) {
            $id = str_pad($id, Invoices::UUID_LENGTH, "0", STR_PAD_LEFT);
        }

        if (!Invoices::isExist($id)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'invoice');
        }

        $invoice = Invoices::findFirst($id);

        if (isset($invoice->expires) && $invoice->expires < time()) {
            return $this->response->error(Response::ERR_INV_EXPIRED);
        }

        if (isset($invoice->requested) && $invoice->requested > 0 && $invoice->requested <= time()) {
            return $this->response->error(Response::ERR_INV_REQUESTED);
        }

        $invoice->requested = time();
        $invoice->payer     = $requester;

        try {

            if ($invoice->update()) {

                $data = [
                    'id'        => $invoice->id,
                    'account'   => $invoice->account,
                    'expires'   => $invoice->expires,
                    'amount'    => $invoice->amount,
                    'payer'     => $invoice->payer,
                    'asset'     => $invoice->asset,
                    'memo'      => $invoice->memo,
                    'requested' => $invoice->requested,
                ];

                return $this->response->single($data);

            }

            $this->logger->emergency('Riak error while updating invoice');
            throw new Exception(Exception::SERVICE_ERROR);

        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function listAction()
    {

        $allowed_types = [
            Account::TYPE_ANONYMOUS,
            Account::TYPE_REGISTERED,
            Account::TYPE_MERCHANT,
            Account::TYPE_EXCHANGE,
            Account::TYPE_DISTRIBUTION,
            Account::TYPE_BANK,
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)){
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $ban_data = InvoicesBans::getDataByID($requester);

        if (!empty($ban_data) && !empty($ban_data->blocked) && $ban_data->blocked > time()) {
            return $this->response->error(Response::ERR_ACC_BLOCKED);
        }

        $limit  = $this->request->get('limit')   ?? null;
        $offset = $this->request->get('offset')  ?? null;

        if (!empty($this->request->get('account_id'))) {
            //get invoices per account
            $invoices = Invoices::findPerAccount($this->request->get('account_id'), $limit, $offset);
        } else {
            //get all invoices
            $invoices = Invoices::find($limit, $offset);
        }

        return $this->response->items($invoices);

    }

    public function bansCreateAction()
    {

        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        //create new ban record
        $account_id = $this->payload->account_id ?? null;
        $seconds    = isset($this->payload->seconds) ? $this->payload->seconds : null; //$seconds can be 0, it means unban

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if ($seconds == null) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'seconds');
        }

        if ($seconds < 0) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'seconds');
        }

        try {
            $ban = new InvoicesBans($account_id);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $ban->blocked = $seconds > 0 ? time() + $seconds : 0;

        try {

            if ($ban->create()) {
                return $this->response->success();
            }

            $this->logger->emergency('Riak error while creating ban account');
            throw new Exception(Exception::SERVICE_ERROR);


        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

//    public function bansGetAction($account_id)
//    {
//
//        $allowed_types = [
//            Account::TYPE_ADMIN
//        ];
//
//        $requester = $this->request->getAccountId();
//
//        if (!$this->isAllowedType($requester, $allowed_types)) {
//            return $this->response->error(Response::ERR_BAD_TYPE);
//        }
//
//        if (empty($account_id)) {
//            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
//        }
//
//        if (!Account::isValidAccountId($account_id)) {
//            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
//        }
//
//        //get ban by account_id
//        $data = InvoicesBans::getDataByID($account_id);
//
//        $seconds_left = 0; //left seconds to unban
//
//        if (!empty($data) && !empty($data->blocked) && $data->blocked > time()) {
//            $seconds_left = $data->blocked - time();
//        }
//
//        return $this->response->single(['seconds_left' => $seconds_left]);
//
//    }

    public function bansListAction()
    {

        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        //list of ban records

        $limit  = $this->request->get('limit') ?? null;
        $offset = $this->request->get('offset')  ?? null;

        $bans   = InvoicesBans::find($limit, $offset);

        return $this->response->items($bans);

    }
}