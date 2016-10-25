<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\InvoiceBans;
use App\Models\Invoices;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;

class InvoiceController extends ControllerBase
{

    public function createAction()
    {

        $allowed_types = [
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

            $this->handleException($e->getCode(), $e->getMessage());

        }
    }

    public function getAction()
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

        if (!empty($this->request->get('id'))) {
            //get invoice by id

            $id = $this->request->get('id');

            if (mb_strlen($id) < Invoices::UUID_LENGTH) {
                $id = str_pad($id, Invoices::UUID_LENGTH, "0", STR_PAD_LEFT);
            }

            if (!Invoices::isExist($id)) {
                return $this->response->error(Response::ERR_NOT_FOUND, 'invoice');
            }

            $invoice = Invoices::get($id);

            if (empty($invoice)) {
                return $this->response->error(Response::ERR_UNKNOWN);
            }

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

                $this->handleException($e->getCode(), $e->getMessage());

            }

        } elseif (!empty($this->request->get('accountId'))) {
            //get invoices per account

            $limit = $this->request->get('limit') ?? null;
            $page  = $this->request->get('page')  ?? null;

            $invoices = Invoices::getListPerAccount($this->request->get('accountId'), $limit, $page);

            return $this->response->items($invoices);

        } else {
            //get all invoices

            $limit = $this->request->get('limit') ?? null;
            $page  = $this->request->get('page')  ?? null;

            $invoices = Invoices::getList($limit, $page);

            return $this->response->items($invoices);

        }

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
        $accountId = $this->payload->accountId ?? null;
        $seconds   = isset($this->payload->seconds) ? $this->payload->seconds : null;

        if ($seconds == null) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'seconds');
        }

        if ($seconds < 0) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'seconds');
        }

        $ban           = new InvoiceBans($accountId);
        $ban->blocked  = $seconds > 0 ? time() + $seconds : 0;

        try {

            if ($ban->create()) {
                return $this->response->single(['message' => 'success']);
            }

            $this->logger->emergency('Riak error while creating ban account');
            throw new Exception(Exception::SERVICE_ERROR);


        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function bansGetAction()
    {

        $allowed_types = [
            Account::TYPE_ADMIN
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        //list of ban records
        $limit = $this->request->get('limit') ?? null;
        $page  = $this->request->get('page')  ?? null;

        $bans  = InvoiceBans::getList($limit, $page);

        return $this->response->items($bans);

    }
}