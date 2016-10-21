<?php

namespace App\Lib;

class Response extends \Phalcon\Http\Response
{
    const ERR_UNKNOWN = 'ERR_UNKNOWN';
    const ERR_NOT_FOUND = 'ERR_NOT_FOUND';
    const ERR_ALREADY_EXISTS = 'ERR_ALREADY_EXISTS';
    const ERR_INV_EXPIRED = 'ERR_INV_EXPIRED';
    const ERR_INV_REQUESTED = 'ERR_INV_REQUESTED';
    const ERR_BAD_PARAM = 'ERR_BAD_PARAM';
    const ERR_EMPTY_PARAM = 'ERR_EMPTY_PARAM';
    const ERR_SERVICE = 'ERR_SERVICE';
    const ERR_BAD_SIGN = 'ERR_BAD_SIGN';

    public function single(array $data = [])
    {
        $data['nonce'] = $this->getDi()->getRequest()->getNonce();

        return $this->setJsonContent($data);
    }

    public function items($items)
    {
        $data = [];
        $data['items'] = $items;
        $data['nonce'] = $this->getDi()->getRequest()->getNonce();

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
            'nonce'   => $this->getDi()->getRequest()->getNonce()
        ])->send();
        exit;
    }
}