<?php

use Phalcon\Di;
use Phalcon\Test\UnitTestCase as PhalconTestCase;
use SWP\Services\RiakDBService;
use GuzzleHttp\Client;
use Smartmoney\Stellar\Account;

abstract class UnitTestCase extends PhalconTestCase
{
    /**
     * @var bool
     */
    private $_loaded = false;
    protected $test_config = null;
    protected $api_host = null;




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

        $test_data = file_get_contents ("./testdata.json");
        $test_data = json_decode($test_data, true);

        if (!$test_data) {
            throw new \PHPUnit_Framework_IncompleteTestError(
                "testdata.json has bad data"
            );
        }

        $this->test_config = $test_data;
        $this->api_host    = 'api.smartmoney.com.ua';

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

    protected function generateAuthSignature($secret_key){

        $secret_key = call_user_func_array("pack", array_merge(array("C*"), $secret_key));
        $public_key = ed25519_publickey($secret_key);

        $client = new Client();

        // send request for take nonce
        $response = $client->request(
            'GET',
            'http://' . $this->api_host . '/nonce',
            [
                'http_errors' => false,
                'query' => ['accountId' => Account::encodeCheck('accountId', base64_encode($public_key))]
            ]
        );

        $stream         = $response->getBody();
        $body           = $stream->getContents();
        $encode_data    = json_decode($body);

        if (empty($encode_data->nonce)) {
            throw new \PHPUnit_Framework_IncompleteTestError(
                "Can not get nonce"
            );
        }

        $nonce = $encode_data->nonce;

        $signature = [
            $nonce,
            base64_encode(ed25519_sign($nonce, $secret_key, $public_key)),
            base64_encode($public_key)
        ];

        return implode(':', $signature);

    }

}