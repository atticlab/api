<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Invoices;
use Basho\Riak\Exception;

class InvoiceController extends ControllerBase
{

    public $request_timestamp;
    public $request_minute_timestamp;
    public $request_day_timestamp;

    public function initialize()
    {

        if (empty($this->request->getClientAddress())) {
            $this->logger->error('Cannot get client ip address');
            return $this->response->setStatusCode(401)->send();
        }

        //get input params
        if (empty($this->payload)) {
            $this->params = $this->request->getPost();
        } else {
            $this->params = (array)$this->payload;
        }

        $this->request_timestamp        = time();
        $this->request_minute_timestamp = $this->request_timestamp - $this->request_timestamp % 60;
        $this->request_day_timestamp    = $this->request_timestamp - $this->request_timestamp % 86400;

    }

    public function createAction()
    {

        $invoice = new Invoices($this->riak);

        $invoice->expires           = $this->request_timestamp + $this->config->invoice->expired;
        $invoice->created           = $this->request_timestamp;
        $invoice->requested         = false;
        $invoice->is_in_statistic   = false;
        $invoice->payer             = false;
        $invoice->memo              = '';

        $invoice->amount  = !empty($this->params['amount']) ? $this->params['amount'] : null;
        $invoice->asset   = !empty($this->params['asset'])  ? $this->params['asset'] : null;

        //TODO get account from auth data (wait from Eugene)
        $invoice->account = !empty($this->_session) && !empty($this->_session->account) ? $this->_session->account : 'GDWWTT7NBH52BAAFHIQR45IRPFYQSKSKU4NIFJ5DHWG3IGVZ7KMAV4U4';

        if (!empty($this->params['memo']) && $this->params['memo'] != '') {
            $invoice->memo = $this->params['memo'];
        }

        try {

            if ($invoice->create()) {
                return $this->response->single(['id' => $invoice->id]);
            } else {
                return $this->response->error(Response::ERR_UNKNOWN);
            }

        } catch (\Exception $e) {

            $msg  = Response::ERR_UNKNOWN;
            $code = Response::ERR_UNKNOWN;

            if (!empty($e->getMessage())) {
                $msg  = $e->getMessage();
                $code = $e->getCode();
            }

            return $this->response->error($code, $msg);
        }
    }

    public function getAction()
    {

        if (!empty($this->request->get('id'))) {
            //get invoice by id

            $id = $this->request->get('id');

            if (mb_strlen($id) < Invoices::UUID_LENGTH) {
                $id = str_pad($id, Invoices::UUID_LENGTH, "0", STR_PAD_LEFT);
            }

            if (!Invoices::isExist($this->riak, $id)) {
                return $this->response->error(Response::ERR_NOT_FOUND, 'invoice');
            }

            $invoice = new Invoices($this->riak, $id);

            if (empty($invoice)) {
                return $this->response->error(Response::ERR_UNKNOWN);
            }

            if (isset($invoice->expires) && $invoice->expires < $this->request_timestamp) {
                return $this->response->error(Response::ERR_INV_EXPIRED);
            }

            if ($invoice->requested && (int)$invoice->requested <= $this->request_timestamp) {
                return $this->response->error(Response::ERR_INV_REQUESTED);
            }

            //set data about request procedure
            $invoice->requested = $this->request_timestamp;

            //TODO get account from auth data (wait from Eugene)
            $invoice->payer = !empty($this->_session) && !empty($this->_session->account) ? $this->_session->account : 'GDWWTT7NBH52BAAFHIQR45IRPFYQSKSKU4NIFJ5DHWG3IGVZ7KMAV4U4';

            try {

                if ($invoice->update()) {

                    $data = [
                        'id'       => $invoice->id,
                        'account'   => $invoice->account,
                        'expires'   => $invoice->expires,
                        'amount'    => $invoice->amount,
                        'payer'     => $invoice->payer,
                        'asset'     => $invoice->asset,
                        'memo'      => $invoice->memo,
                        'requested' => $invoice->requested,
                    ];

                    return $this->response->single($data);

                } else {
                    return $this->response->error(Response::ERR_UNKNOWN);
                }

            } catch (\Exception $e) {

                $msg  = Response::ERR_UNKNOWN;
                $code = Response::ERR_UNKNOWN;

                if (!empty($e->getMessage())) {
                    $msg  = $e->getMessage();
                    $code = $e->getCode();
                }

                return $this->response->error($code, $msg);
            }

        } elseif (!empty($this->request->get('accountId'))) {
            //get invoices per account
            $invoices = Invoices::getperaccount($this->riak, $this->request->get('accountId'));

            return $this->response->items($invoices);

        } else {
            //get all invoices
            $invoices = Invoices::get($this->riak);

            return $this->response->items($invoices);

        }

    }

    public function notFoundAction()
    {
        return $this->response->error(Response::ERR_NOT_FOUND);
    }
}