<?php

namespace App\Lib;

class Exception extends \Exception
{
    const BAD_PARAM     = 'BAD_PARAM';
    const EMPTY_PARAM   = 'EMPTY_PARAM';
    const UNKNOWN       = 'UNKNOWN';
    const SERVICE_ERROR = 'SERVICE_ERROR';
    const NOT_FOUND     = 'NOT_FOUND';

    protected $code;

    public function __construct($err_code, $message = null)
    {
        if (empty($err_code) || !defined('self::' . $err_code)) {
            $err_code = self::UNKNOWN;
        }

        $this->code = $err_code;
        $this->message = $message;
    }
}