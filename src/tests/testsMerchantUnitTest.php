<?php

namespace Merchant;

use \App\Models\MerchantStores;
use \App\Models\MerchantOrders;
use \Phalcon\DI;
use Smartmoney\Stellar\Account;
use GuzzleHttp\Client;
use App\Lib\Response;

/**
 * Class UnitTest
 */
class MerchantUnitTest extends \UnitTestCase
{

    public static function CreateStoreProvider()
    {

        return array(

            //example: array (requester_type, url, name, http_code, err_code, message)

            //no url
            array('merchant', null, 'store_name', 400, Response::ERR_EMPTY_PARAM, 'url'),

            //bad url
            array('merchant', 'bad_url', 'store_name', 400, Response::ERR_BAD_PARAM, 'url'),

            //no name
            array('merchant', 'test.google.com', null, 400, Response::ERR_EMPTY_PARAM, 'name'),

            //bad name
            array('merchant', 'test.google.com', 'name_more_than_20_symbols', 400, Response::ERR_BAD_PARAM, 'name'),

            //bad type
            array('anonym', 'test.google.com', 'store_name', 400, Response::ERR_BAD_TYPE, null),

            //all ok - will create store
            array('merchant', 'test.google.com', 'store_name', 200, null, null),

        );

    }

    /**
     * @dataProvider CreateStoreProvider
     */
    public function testCreateStore($requester_type, $url, $name, $http_code, $err_code, $msg)
    {

        parent::setUp();

        $client = new Client();

        //[TEST] create new store ------------------

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://' . $this->api_host .'/merchant/stores',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "url"  => $url,
                    "name" => $name
                ]
            ]
        );

        $real_http_code = $response->getStatusCode();
        $stream         = $response->getBody();
        $body           = $stream->getContents();
        $encode_data    = json_decode($body);

        //test http code
        $this->assertEquals(
            $http_code,
            $real_http_code
        );

        $this->assertTrue(
            !empty($encode_data)
        );

        if ($err_code) {

            //test error data structure
            $this->assertTrue(
                property_exists($encode_data, 'error')
            );

            //test error code
            $this->assertEquals(
                $err_code,
                $encode_data->error
            );
        }

        //test message
        if ($msg) {

            //test message data structure
            $this->assertTrue(
                property_exists($encode_data, 'message')
            );

            $this->assertEquals(
                $msg,
                $encode_data->message
            );
        }

        //when we make test that success create store
        if ($real_http_code == 200) {

            $url = MerchantStores::formatUrl($url);

            //base64 is needed for riak!!!
            //"url" can not be used like primary key
            //because riak dont save that object (but will return success!!!)
            $url = base64_encode($url);

            //delete test store
            $cur_store = MerchantStores::findFirst($url);
            if ($cur_store) {
                $cur_store->delete();
            }

        }

    }

    public static function GetStoresProvider()
    {

        return array(

            //example: array (requester_type, http_code, err_code)

            //bad account type
            array('anonym', 400, Response::ERR_BAD_TYPE),

            //all ok - will get list of companies
            array('merchant', 200, null),

        );

    }

    /**
     * @dataProvider GetStoresProvider
     */
    public function testGetStores($requester_type, $http_code, $err_code){

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //[TEST] get all stores -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host .'/merchant/stores',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false
            ]
        );

        $real_http_code = $response->getStatusCode();
        $stream         = $response->getBody();
        $body           = $stream->getContents();
        $encode_data    = json_decode($body);

        $this->assertEquals(
            $http_code,
            $real_http_code
        );

        if ($err_code) {

            //test error data structure
            $this->assertTrue(
                property_exists($encode_data, 'error')
            );

            //test error code
            $this->assertEquals(
                $err_code,
                $encode_data->error
            );
        }

        if ($real_http_code == 200) {

            $this->assertTrue(
                property_exists($encode_data, 'items')
            );

            $this->assertInternalType('object', $encode_data);
            $this->assertInternalType('array',  $encode_data->items);

        }

    }

}