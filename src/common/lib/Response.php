<?php

namespace App\Lib;

class Response extends \Phalcon\Http\Response
{

    const ERR_UNKNOWN               = 'unknown_error';
    const ERR_NOT_FOUND             = 'not_found';
    const ERR_ALREADY_EXIST         = 'already_exist';
    const ERR_INV_EXPIRED           = 'invoice_expired';
    const ERR_INV_REQUESTED         = 'invoice_requested';
    const ERR_BAD_PARAM             = 'bad_param';
    const ERR_EMPTY_PARAM           = 'empty_param';

    public static $_http_codes = [
        self::ERR_NOT_FOUND => 404,
        self::ERR_ALREADY_EXIST => 403,
        self::ERR_EMPTY_PARAM => 403,
        self::ERR_BAD_PARAM => 403,
        self::ERR_INV_REQUESTED => 403,
        self::ERR_INV_EXPIRED => 403,
        self::ERR_UNKNOWN => 500,
    ];

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
        return $this->setJsonContent($data);
    }

    public function error($err_code, $msg = '')
    {

        if (!array_key_exists($err_code, self::$_http_codes)) {
            throw new \Exception($err_code . ' - Unknown error code');
        }

        $this->setStatusCode(self::$_http_codes[$err_code]);

        return $this->setJsonContent([
            'error'   => $err_code,
            'message' => $msg,
            'nonce'   => $this->getDi()->getRequest()->getNonce()
        ]);
    }
}