<?php

$router->add('/', [
    'controller' => 'index',
    'action' => 'index'
]);

//nonce
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

//enrollments
$router->addGet('/enrollments', [
    'controller' => 'enrollments',
    'action' => 'list',
]);

//get agent enrollment data (with agent data) by enrollment token and company_code (send as post parameter)
$router->addGet('/enrollment/agent/get/{id}', [
    'controller' => 'enrollments',
    'action' => 'getAgentEnrollment',
]);

//get user enrollment data (with user data) by enrollment token
$router->addGet('/enrollment/user/get/{id}', [
    'controller' => 'enrollments',
    'action' => 'getUserEnrollment',
]);

//can call anyone with token, nonce not need, account type dont checked
$router->addPost('/enrollments/decline/{id}', [
    'controller' => 'enrollments',
    'action' => 'decline',
]);

//can call anyone with token, nonce not need, account type dont checked
$router->addPost('/enrollments/accept/{id}', [
    'controller' => 'enrollments',
    'action' => 'accept',
]);

$router->addPost('/enrollments/approve/{id}', [
    'controller' => 'enrollments',
    'action' => 'approve',
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

$router->addGet('/bans', [
    'controller' => 'bans',
    'action' => 'bans'
]);

$router->addGet('/bans/add', [
    'controller' => 'bans',
    'action' => 'add'
]);

$router->addGet('/bans/delete', [
    'controller' => 'bans',
    'action' => 'delete'
]);

//404 not found
$router->notFound([
    "controller" => "index",
    "action" => "notFound"
]);