<?php

$config = new \Phalcon\Config([
    "modules" => ['api'],

    "invoice" => [
        'expired' => 86400
    ],

    "nonce" => [
        'ttl' => 10 * 60
    ]
]);




