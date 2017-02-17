<?php

$config = new \Phalcon\Config([
    "modules" => ['api'],

    "invoice" => [
        'expired' => 86400
    ],

    "nonce" => [
        'ttl' => 30 * 60
    ],

    "horizon" => [
        "host" => rtrim(getenv("HORIZON_HOST"), '/'),
    ],

    "riak" => [
        "default_limit"        => 25,
        "search_index_suffics" => '_si',
        "yokozuna_sufficses"   => [
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
        //"transaction_url" => 'http://merchant.euah.pw:8010' . '/transaction'
    ],

    "master_key" => getenv("MASTER_KEY"),

    "weights" => [
        'admin' => 1
    ],

    "smtp" => [
        'host'     => 'smtp.gmail.com',
        'port'     => '465',
        'security' => 'ssl',
        'username' => 'attic.it.lab@gmail.com',
        'password' => 'atticlab/*-2020',
    ],

    "ban" => [
        'short'           => 60 * 60,             //1 hour
        'long'            => 60 * 60 * 24 * 365,  //1 year
        'req_per_minutes' => 10,                  //bad request per munute
        'req_per_day'     => 100,                 //bad request per day
    ],

    "sms" => [
        'max_phone_accounts' => 4,
        'otp_length' => 6,
        'url'   => 'http://turbosms.in.ua/api/wsdl.html',
        'sender' => 'Msg',
        'msg'   => 'Your registration code is ',
        'otp_ttl'   => 10 * 60, //10 minutes
        'phone_prefix' => '+38',
        'auth' => [
            'login' => getenv("SMS_AUTH_LOGIN"),
            'password' => getenv("SMS_AUTH_PASS")
        ]
    ]
]);




