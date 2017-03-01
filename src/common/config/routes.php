<?php

$router->add('/', [
    'controller' => 'index',
    'action'     => 'index'
]);

//nonce
$router->add('/nonce', [
    'controller' => 'nonce',
    'action'     => 'index'
]);

//enrollments
$router->addGet('/enrollments', [
    'controller' => 'enrollments',
    'action'     => 'list',
]);

//get agent enrollment data (with agent data) by enrollment token and company_code (send as post parameter)
$router->addGet('/enrollment/agent/get/{id}', [
    'controller' => 'enrollments',
    'action'     => 'getAgentEnrollment',
]);

//get user enrollment data (with user data) by enrollment token
$router->addGet('/enrollment/user/get/{id}', [
    'controller' => 'enrollments',
    'action'     => 'getUserEnrollment',
]);

//can call anyone with token, nonce not need, account type dont checked
$router->addPost('/enrollments/decline/{id}', [
    'controller' => 'enrollments',
    'action'     => 'decline',
]);

//can call anyone with token, nonce not need, account type dont checked
$router->addPost('/enrollments/accept/{id}', [
    'controller' => 'enrollments',
    'action'     => 'accept',
]);

$router->addPost('/enrollments/approve/{id}', [
    'controller' => 'enrollments',
    'action'     => 'approve',
]);

//admins
$router->addGet('/admins', [
    'controller' => 'admins',
    'action'     => 'list',
]);

$router->addPost('/admins', [
    'controller' => 'admins',
    'action'     => 'create',
]);

$router->addGet('/admins/{account_id}', [
    'controller' => 'admins',
    'action'     => 'get',
]);

$router->addPost('/admins/delete', [
    'controller' => 'admins',
    'action'     => 'delete',
]);

//agents
$router->addGet('/agents', [
    'controller' => 'agents',
    'action'     => 'list',
]);

$router->addPost('/agents', [
    'controller' => 'agents',
    'action'     => 'create',
]);

//bans
$router->addGet('/bans', [
    'controller' => 'bans',
    'action'     => 'list'
]);

$router->addPost('/bans', [
    'controller' => 'bans',
    'action'     => 'create'
]);

$router->addPost('/bans/delete', [
    'controller' => 'bans',
    'action'     => 'delete'
]);

//cards
$router->addGet('/cards/{id}', [
    'controller' => 'cards',
    'action'     => 'get',
]);

$router->addGet('/cards', [
    'controller' => 'cards',
    'action'     => 'list',
]);

$router->addPost('/cards', [
    'controller' => 'cards',
    'action'     => 'createCards',
]);

//companies
$router->addGet('/companies/{id}', [
    'controller' => 'companies',
    'action'     => 'get',
]);

$router->addGet('/companies', [
    'controller' => 'companies',
    'action'     => 'list',
]);

$router->addPost('/companies', [
    'controller' => 'companies',
    'action'     => 'create',
]);

//invoices
$router->addGet('/invoices', [
    'controller' => 'invoices',
    'action'     => 'list',
]);

$router->addGet('/invoices/{id}', [
    'controller' => 'invoices',
    'action'     => 'get'
]);

$router->addPost('/invoices', [
    'controller' => 'invoices',
    'action'     => 'create',
]);

$router->addGet('/invoices/statistics', [
    'controller' => 'invoices',
    'action'     => 'statistics',
]);

//merchant
$router->addGet('/merchant/stores', [
    'controller' => 'merchant',
    'action'     => 'storesList',
]);

$router->addPost('/merchant/stores', [
    'controller' => 'merchant',
    'action'     => 'storesCreate'
]);

$router->addGet('/merchant/orders', [
    'controller' => 'merchant',
    'action'     => 'ordersList'
]);

$router->addGet('/merchant/orders/{id}', [
    'controller' => 'merchant',
    'action'     => 'ordersGet'
]);

$router->addPost('/merchant/orders', [
    'controller' => 'merchant',
    'action'     => 'ordersCreate'
]);

//registered users
$router->addGet('/regusers', [
    'controller' => 'regusers',
    'action'     => 'list',
]);

$router->addPost('/regusers', [
    'controller' => 'regusers',
    'action'     => 'create',
]);

//wallets (nonce not need, account type dont checked)
$router->addGet('/wallets', [
    'controller' => 'wallets',
    'action'     => 'index',
]);

$router->addGet('/wallets/getkdf', [
    'controller' => 'wallets',
    'action'     => 'getkdf',
]);

$router->addPost('/wallets/getparams', [
    'controller' => 'wallets',
    'action'     => 'getparams',
]);

$router->addPost('/wallets/create', [
    'controller' => 'wallets',
    'action'     => 'create',
]);

$router->addPost('/wallets/createphone', [
    'controller' => 'wallets',
    'action'     => 'createWithPhone',
]);

$router->addPost('/wallets/get', [
    'controller' => 'wallets',
    'action'     => 'get',
]);

$router->addPost('/wallets/update', [
    'controller' => 'wallets',
    'action'     => 'update',
]);

$router->addPost('/wallets/updatepassword', [
    'controller' => 'wallets',
    'action'     => 'updatePassword',
]);

$router->addPost('/wallets/notexist', [
    'controller' => 'wallets',
    'action'     => 'notExist',
]);

$router->addPost('/wallets/getdata', [
    'controller' => 'wallets',
    'action'     => 'getWalletData',
]);

//sms
$router->addGet('/sms/{account_id}', [
    'controller' => 'sms',
    'action' => 'get',
]);

$router->addGet('/sms/listbyphone/{phone}', [
    'controller' => 'sms',
    'action' => 'listByPhone',
]);

$router->addPost('/sms', [
    'controller' => 'sms',
    'action' => 'createSms',
]);

$router->addPost('/sms/submitOTP', [
    'controller' => 'sms',
    'action' => 'submitOTP',
]);

$router->addPost('/sms/resend', [
    'controller' => 'sms',
    'action' => 'resend',
]);

$router->addPost('/sms/check', [
    'controller' => 'sms',
    'action' => 'check',
]);

//404 not found
$router->notFound([
    "controller" => "index",
    "action"     => "notFound"
]);