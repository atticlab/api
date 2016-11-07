<?php

$router->add('/', [
    'controller' => 'index',
    'action' => 'index'
]);

$router->add('/nonce', [
    'controller' => 'nonce',
    'action' => 'index'
]);

//invoices
$router->addGet('/invoices', [
    'controller' => 'invoices',
    'action' => 'list',
]);

$router->addGet('/invoices/{id}', [
    'controller' => 'invoices',
    'action' => 'get'
]);

$router->addPost('/invoices', [
    'controller' => 'invoices',
    'action' => 'create',
]);

$router->addGet('/invoices/bans', [
    'controller' => 'invoices',
    'action' => 'bansList',
]);

//    $router->addGet('/invoices/bans/{id}', [
//        'controller' => 'invoices',
//        'action' => 'bansGet',
//    ]);

$router->addPost('/invoices/bans', [
    'controller' => 'invoices',
    'action' => 'bansCreate',
]);

//companies
$router->addGet('/companies/{id}', [
    'controller' => 'companies',
    'action' => 'get',
]);

$router->addGet('/companies', [
    'controller' => 'companies',
    'action' => 'list',
]);

$router->addPost('/companies', [
    'controller' => 'companies',
    'action' => 'create',
]);

//agents
$router->addGet('/agents', [
    'controller' => 'agents',
    'action' => 'list',
]);

$router->addPost('/agents', [
    'controller' => 'agents',
    'action' => 'create',
]);

//registered users
$router->addGet('/reguser', [
    'controller' => 'regusers',
    'action' => 'get',
]);

$router->addGet('/regusers', [
    'controller' => 'regusers',
    'action' => 'list',
]);

$router->addPost('/reguser', [
    'controller' => 'regusers',
    'action' => 'create',
]);

//cards
$router->addGet('/cards/{id}', [
    'controller' => 'cards',
    'action' => 'get',
]);

$router->addGet('/cards', [
    'controller' => 'cards',
    'action' => 'list',
]);

$router->addPost('/cards', [
    'controller' => 'cards',
    'action' => 'create',
]);

$router->notFound([
    "controller" => "index",
    "action" => "notFound"
]);

//merchant
$router->addGet('/merchant/stores', [
    'controller' => 'merchant',
    'action' => 'storesList',
]);

$router->addPost('/merchant/stores', [
    'controller' => 'merchant',
    'action' => 'storesCreate'
]);

$router->addGet('/merchant/stores/{id}/orders', [
    'controller' => 'merchant',
    'action' => 'ordersList'
]);

$router->addGet('/merchant/orders/{id}', [
    'controller' => 'merchant',
    'action' => 'ordersGet'
]);

$router->addPost('/merchant/orders', [
    'controller' => 'merchant',
    'action' => 'ordersCreate'
]);

//404 not found
$router->notFound([
    "controller" => "index",
    "action" => "notFound"
]);