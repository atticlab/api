<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Invoices;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;

class InvoicesController extends ControllerBase
{

    public function createAction() {
        $allowed_types = [
            //TODO: REMOVE DESTRIBUTION AGENT AFTER CRYPTO_UAH realese
            Account::TYPE_DISTRIBUTION,
            //---------------------------------------------------------
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
        $asset = $this->payload->asset  ?? null;
        if (!empty($asset)) {
            if (Account::getAccountBalances($requester, $this->config->horizon->host, $this->config->horizon->port, $asset) === false) {
                return $this->response->error(Response::ERR_BAD_PARAM, 'asset');
            }
        }
        $invoice                    = new Invoices();
        $invoice->expires           = time() + $this->config->invoice->expired;
        $invoice->created           = time();
        $invoice->requested         = false;
        $invoice->is_in_statistic   = false;
        $invoice->payer             = false;
        $invoice->amount            = $this->payload->amount ?? null;
        $invoice->asset             = $asset;
        $invoice->account           = $requester;
        $invoice->memo              = $this->payload->memo ?? null;
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

    public function getAction($id) {
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
        if (!empty($invoice->asset)) {
            if (Account::getAccountBalances($requester, $this->config->horizon->host, $this->config->horizon->port, $invoice->asset) === false) {
                return $this->response->error(Response::ERR_BAD_PARAM, 'asset');
            }
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

    public function listAction() {
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
}