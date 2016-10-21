<?php

namespace App\lib;

class Exception extends \Exception
{
    const BAD_PARAM        = 'EMPTY_PARAM';
    const EMPTY_PARAM      = 'BAD PARAM';
    const UNKNOWN          = 'UNKNOWN';

    public function __construct($err_code)
    {
        if (empty($err_code) || !defined(self::$err_code)) {
            return parent::__construct(self::UNKNOWN);
        }

        return parent::__construct($err_code);
    }
}