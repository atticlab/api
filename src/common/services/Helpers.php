<?php

namespace App\Services;

use App\Lib\Exception;
use Phalcon\DI;

class Helpers
{
    public static function clearYzSuffixes($data) {
        if (is_object($data)) {
            //single item
            self::clearRiakObject($data);
        } elseif (is_array($data)) {
            //array of items
            foreach ($data as &$item) {
                self::clearRiakObject($item);
            }
        }

        return $data;
    }

    private static function clearRiakObject(&$object) {
        $config = DI::getDefault()->get('config');
        $logger = DI::getDefault()->get('logger');
        if (!is_object($object)) {
            $logger->error('Can not clear yokozuna sufficses. Object expected, get ' . gettype($object));
            throw new Exception('Can not clear yokozuna sufficses. Object expected, get ' . gettype($object));
        }
        foreach (get_object_vars($object) as $key => $value) {
            if (mb_substr($key, -2) && mb_substr($key, 0, -2) && in_array(mb_substr($key, -2), (array)$config->riak->yokozuna_sufficses)) {
                unset($object->{$key});
                $object->{mb_substr($key, 0, -2)} = $value;
            }
        }
    }
}