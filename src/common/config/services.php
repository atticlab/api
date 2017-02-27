<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\IntrospectionProcessor;
use Basho\Riak;
use Basho\Riak\Node;

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
    $this->nodes = (new Node\Builder)
        ->onPort(getenv('RIAK_PORT'))
        ->buildCluster(array(getenv('RIAK_HOST')));

    return new Riak($this->nodes);
});

# Memcached
$di->setShared('memcached', function () use ($config) {
    $m = new \Memcached();
    $m->addServer('memcached', 11211);
    return $m;
});

# Mailer (requires composer component)
$di->setShared('mailer', function () use ($config) {
    $mailer = new \App\Lib\Mailer([
        'templates' => APP_PATH . 'common/emails/',
        'host'      => $config->smtp->host,
        'port'      => $config->smtp->port,
        'username'  => $config->smtp->username,
        'password'  => $config->smtp->password,
        'security'  => $config->smtp->security
    ]);

    if (!empty($config->project->admin_email) && !empty($config->project->admin_name)) {
        $mailer->setFrom($config->project->admin_email, $config->project->admin_name);
    }

    return $mailer;
});


# Config
$di->setShared('config', $config);