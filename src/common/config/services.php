<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use SWP\Services\RiakDBService;

# Logger
$di->setShared('logger', function () use ($config, $di) {
    $format = new Monolog\Formatter\LineFormatter("[%datetime%] %level_name%: %message% %context%\n");

    $stdout = new StreamHandler('php://stdout', Logger::DEBUG);
    $stdout->setFormatter($format);

    $stream = new StreamHandler(ini_get('error_log'), Logger::DEBUG); // use Logger::WARNING for production
    $stream->setFormatter($format);

    $log = new Logger(__FUNCTION__);
    $log->pushProcessor(new IntrospectionProcessor());
    $log->pushHandler($stdout);
    $log->pushHandler($stream);

    return $log;
});

$di->getLogger();

$di->setShared('crypt', function () use ($config) {
    $crypt = new \Phalcon\Crypt();
    $crypt->setMode(MCRYPT_MODE_CFB);

//    $crypt->setKey($config->project->crypt_key);

    return $crypt;
});

# Session
$di->setShared('session', function () use ($config) {
    $session = new \Phalcon\Session\Adapter\Files();
    $session->start();

    return $session;
});

# RiakDB
$di->setShared('riak', function () use ($config) {
    $riak = new RiakDBService(
        8098,
        array(getenv('RIAK_HOST'))
    );
    return $riak->db;
});

# Memcached
$di->setShared('memcached', function () use ($config) {
    $m = new \Memcached();
    $m->addServer('memcached', 11211);
    return $m;
});


# Config
$di->setShared('config', $config);