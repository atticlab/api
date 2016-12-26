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
        "host" => 'blockchain.euah.cf',
        "port" => 80
    ],

    "cards" => [
        "operations_limit" => 10
    ],

    "merchant" => [
        "transaction_url" => 'http://merchant.euah.cf/transaction'
    ],

    "master_key" => 'GAWIB7ETYGSWULO4VB7D6S42YLPGIC7TY7Y2SSJKVOTMQXV5TILYWBUA',

    "weights" => [
        'admin' => 1
    ],

    "smtp"            => [
        'host'     => 'smtp.gmail.com',
        'port'     => '465',
        'security' => 'ssl',
        'username' => 'attic.it.lab@gmail.com',
        'password' => 'atticlab/*-2020',
    ],
    
    "ban" => [
        'short'             => 60 * 60,             //1 hour
        'long'              => 60 * 60 * 24 * 365,  //1 year
        'req_per_minutes'   => 10,                  //bad request per munute
        'req_per_day'      =>  100,                 //bad request per day 
    ],
]);




