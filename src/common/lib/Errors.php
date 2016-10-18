<?php

namespace App\Lib;

use \Phalcon\Di;

class Errors
{
    const NOT_FOUND = 'not_found';

    private static $_http_codes = [
        self::NOT_FOUND => 404,
    ];

    public static function returnJson($err_code, $msg = '')
    {
        if (!array_key_exists($err_code, self::$_http_codes)) {
            throw new \Exception('Unknown error code');
        }

        $response = Di::getDefault()->getResponse();
        $response->setStatusCode(self::$_http_codes[$err_code]);
        $response->setJsonContent([
            'code'    => $err_code,
            'message' => $msg
        ]);

        return $response;
    }
}