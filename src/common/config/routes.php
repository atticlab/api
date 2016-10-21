<?php
	$router->add('/', [
		'controller' => 'index',
		'action' => 'index'
	]);

    $router->add('/nonce', [
        'controller' => 'nonce',
        'action' => 'index'
    ]);

    $router->addGet('/invoice', [
        'controller' => 'invoice',
        'action' => 'get',
    ]);

	$router->addPost('/invoice', [
		'controller' => 'invoice',
		'action' => 'create',
	]);

    $router->addGet('/companies', [
        'controller' => 'companies',
        'action' => 'get',
    ]);

	$router->addPost('/companies', [
		'controller' => 'companies',
		'action' => 'create',
	]);

	$router->notFound([
	    "controller" => "index",
	    "action" => "notFound"
	]);