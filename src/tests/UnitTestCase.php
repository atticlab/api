<?php

use Phalcon\Di;
use Phalcon\Test\UnitTestCase as PhalconTestCase;
use SWP\Services\RiakDBService;

abstract class UnitTestCase extends PhalconTestCase
{
    /**
     * @var bool
     */
    private $_loaded = false;

    //how to sign new nonce for test
    //ed25519_sign($data, $secret, $public);


    public function setUp()
    {

        parent::setUp();

        // Load any additional services that might be required during testing
        $di = Di::getDefault();

        # RiakDB
        $di->setShared('riak', function () {
            $riak = new RiakDBService(
                8098,
                array(getenv('RIAK_HOST'))
            );
            return $riak->db;
        });

        $di->set('request', function () {
            return new \App\Lib\Request();
        });

        $di->set('response', function () {
            return new \App\Lib\Response();
        });

        $this->setDi($di);

        $this->_loaded = true;
    }

    /**
     * Check if the test case is setup properly
     *
     * @throws \PHPUnit_Framework_IncompleteTestError;
     */
    public function __destruct()
    {
        if (!$this->_loaded) {
            throw new \PHPUnit_Framework_IncompleteTestError(
                "Please run parent::setUp()."
            );
        }
    }
}