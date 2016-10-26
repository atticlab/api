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
        'action' => 'get',
    ]);

    $router->addPost('/agents', [
        'controller' => 'agents',
        'action' => 'create',
    ]);

    $router->addPost('/agents/enrollments', [
        'controller' => 'agents',
        'action' => 'enrollmentsCreate',
    ]);

    $router->addGet('/agents/enrollments', [
        'controller' => 'agents',
        'action' => 'enrollmentsList',
    ]);

    $router->addPost('/agents/enrollments/approve', [
        'controller' => 'agents',
        'action' => 'enrollmentsApprove',
    ]);

    //registered users
    $router->addGet('/regusers', [
        'controller' => 'regusers',
        'action' => 'get',
    ]);

    $router->addPost('/regusers', [
        'controller' => 'regusers',
        'action' => 'create',
    ]);

    $router->addPost('/regusers/enrollments', [
        'controller' => 'regusers',
        'action' => 'enrollmentsCreate',
    ]);

    $router->addGet('/regusers/enrollments', [
        'controller' => 'regusers',
        'action' => 'enrollmentsList',
    ]);

    $router->addPost('/regusers/enrollments/approve', [
        'controller' => 'regusers',
        'action' => 'enrollmentsApprove',
    ]);

    //cards
    $router->addGet('/cards', [
        'controller' => 'cards',
        'action' => 'get',
    ]);

    $router->addPost('/cards', [
        'controller' => 'cards',
        'action' => 'create',
    ]);

	$router->notFound([
	    "controller" => "index",
	    "action" => "notFound"
	]);