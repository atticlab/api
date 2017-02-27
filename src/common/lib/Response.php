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
    const ERR_TOO_MANY_ACCS         = 'ERR_TOO_MANY_ACCS';
    const ERR_SMS_NO_FUNDS          = 'ERR_SMS_NO_FUNDS';
    const ERR_SMS_SEND              = 'ERR_SMS_SEND';
    const ERR_OTP_TTL               = 'ERR_OTP_TTL';
    const ERR_ALREADY_TAKEN         = 'ERR_ALREADY_TAKEN';

    public function json($data = null, $add_nonce = true)
    {
        $resp = [];
        $resp['data'] = $data;

        if ($add_nonce) {
            $resp['nonce']  = $this->getDi()->getRequest()->getNonce();
            $resp['ttl']    = $this->getDi()->getConfig()->nonce->ttl;
        }

        $resp['status'] = 'success';

        $this->setJsonContent($resp)->send();
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
}