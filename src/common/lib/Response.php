<?php

namespace App\Lib;

use Phalcon\DI;

class Response extends \Phalcon\Http\Response
{
    const ERR_UNKNOWN = 'ERR_UNKNOWN';
    const ERR_NOT_FOUND = 'ERR_NOT_FOUND';
    const ERR_ALREADY_EXISTS = 'ERR_ALREADY_EXISTS';
    const ERR_INV_EXPIRED = 'ERR_INV_EXPIRED';
    const ERR_INV_REQUESTED = 'ERR_INV_REQUESTED';
    const ERR_IP_BLOCKED = 'ERR_IP_BLOCKED';
    const ERR_BAD_PARAM = 'ERR_BAD_PARAM';
    const ERR_EMPTY_PARAM = 'ERR_EMPTY_PARAM';
    const ERR_SERVICE = 'ERR_SERVICE';
    const ERR_BAD_SIGN = 'ERR_BAD_SIGN';
    const ERR_BAD_TYPE = 'ERR_BAD_TYPE';
    const ERR_TX = 'ERR_TX';

    public function single(array $data = [], $add_nonce = true)
    {
        $data = $this->prepareDataSuccessResponse($data, $add_nonce);
        $this->setJsonContent($data)->send();
        exit;
    }

    public function items($items, $add_nonce = true)
    {
        $data = [];
        $data['items'] = $items;
        $data = $this->prepareDataSuccessResponse($data, $add_nonce);
        $this->setJsonContent($data)->send();
        exit;
    }

    public function error($err_code, $msg = '', $http_code = 400)
    {
        DI::getDefault()->get('logger')->info('API error', [$err_code, $msg]);
        if (!defined('self::' . $err_code)) {
            throw new \Exception($err_code . ' - Unknown error code');
        }
        $this->setStatusCode($http_code);

        $this->setJsonContent([
            'error' => $err_code,
            'message' => $msg,
        ])->send();
        exit;
    }

    private function prepareDataSuccessResponse(array $data = [], $add_nonce)
    {
        if ($add_nonce) {
            $config         = $this->getDi()->getConfig();
            $data['nonce']  = $this->getDi()->getRequest()->getNonce();
            $data['ttl']    = $config->nonce->ttl;
        }

        $data['status'] = 'success';

        return $data;
    }
}