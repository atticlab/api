<?php

namespace App\Lib;

class Response extends \Phalcon\Http\Response
{
    const ERR_UNKNOWN               = 'ERR_UNKNOWN';
    const ERR_NOT_FOUND             = 'ERR_NOT_FOUND';
    const ERR_ALREADY_EXISTS        = 'ERR_ALREADY_EXISTS';
    const ERR_INV_EXPIRED           = 'ERR_INV_EXPIRED';
    const ERR_INV_REQUESTED         = 'ERR_INV_REQUESTED';
    const ERR_IP_BLOCKED            = 'ERR_IP_BLOCKED';
    const ERR_BAD_PARAM             = 'ERR_BAD_PARAM';
    const ERR_EMPTY_PARAM           = 'ERR_EMPTY_PARAM';
    const ERR_SERVICE               = 'ERR_SERVICE';
    const ERR_BAD_SIGN              = 'ERR_BAD_SIGN';
    const ERR_BAD_TYPE              = 'ERR_BAD_TYPE';
    const ERR_TX                    = 'ERR_TX';

    public function single(array $data = [])
    {
        $data = $this->prepareDataSuccessResponse($data);
        $this->setJsonContent($data)->send();
        exit;
    }

    public function items($items)
    {
        $data = [];
        $data['items'] = $items;
        $data = $this->prepareDataSuccessResponse($data);
        $this->setJsonContent($data)->send();
        exit;
    }

    public function success()
    {
        $data = $this->prepareDataSuccessResponse();
        $this->setJsonContent($data)->send();
        exit;
    }

    public function error($err_code, $msg = '')
    {
        if (!defined('self::' . $err_code)) {
            throw new \Exception($err_code . ' - Unknown error code');
        }

        $this->setStatusCode(400);

        $this->setJsonContent([
            'error'   => $err_code,
            'message' => $msg,
        ])->send();
        exit;
    }

    private function prepareDataSuccessResponse(array $data = []) {

        $config = $this->getDi()->getConfig();

        $data['nonce']   = $this->getDi()->getRequest()->getNonce();
        $data['ttl']     = $config->nonce->ttl;
        $data['message'] = 'success';

        return $data;
    }
}