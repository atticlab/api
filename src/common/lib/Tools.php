<?php

namespace App\Lib;

class Tools
{
    public static function readMore($string, $limit = 300, $more_text = '...')
    {
        if (empty($string)) {
            return false;
        }

        if (strlen($string) > $limit) {
            return substr($string, 0, $limit) . '...';
        }

        return $string;
    }
}