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
        "host" => 'blockchain.smartmoney.com.ua',
        "port" => 80
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

]);




