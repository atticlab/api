<?php

namespace App\Lib;

class Response extends \Phalcon\Http\Response
{
    const ERR_NOT_FOUND = 'not_found';

    private $http_codes = [
        self::ERR_NOT_FOUND => 404,
    ];

    public function success(array $data = [])
    {
        $data['nonce'] = $this->getDi()->getRequest()->getNonce();
        return $this->setJsonContent($data);
    }

    public function error($err_code, $msg = '')
    {
        if (!array_key_exists($err_code, $this->http_codes)) {
            throw new \Exception('Unknown error code');
        }

        $this->setStatusCode($this->http_codes[$err_code]);

        return $this->setJsonContent([
            'error'   => $err_code,
            'message' => $msg,
            'nonce'   => $this->getDi()->getRequest()->getNonce()
        ]);
    }
}