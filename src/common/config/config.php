<?php

$config = new \Phalcon\Config([
    "modules" => ['api'],

    "invoice" => [
        'expired' => 86400
    ],

    "nonce" => [
        'ttl' => 10 * 60
    ],

    "horizon" => [
        "host" => rtrim(getenv("HORIZON_HOST"), '/'),
    ],

    "asset" => 'EUAH',

    "riak" => [
        "default_limit" => 25,
        "search_index_suffics" => '_si',
        "yokozuna_sufficses" => [
            "_b",
            "_i",
            "_f",
            "_s"
        ],
    ],

    "cards" => [
        "operations_limit" => 10
    ],

    "merchant" => [
        "transaction_url" => rtrim(getenv("MERCHANT_HOST"), '/') . '/transaction'
    ],

    "master_key" => getenv("MASTER_KEY"),

    "weights" => [
        'admin' => 1
    ],

    "smtp"            => [
        'host'     => trim(getenv("SMTP_HOST")),
        'port'     => trim(getenv("SMTP_PORT")),
        'security' => trim(getenv("SMTP_SECURITY")),
        'username' => trim(getenv("SMTP_USER")),
        'password' => trim(getenv("SMTP_PASS")),
    ],
    
    "ban" => [
        'short'             => 60 * 60,             //1 hour
        'long'              => 60 * 60 * 24 * 365,  //1 year
        'req_per_minutes'   => 10,                  //bad request per munute
        'req_per_day'      =>  100,                 //bad request per day 
    ],
]);




