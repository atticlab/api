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
            array('merchant', 'google6.com', null, 400, Response::ERR_EMPTY_PARAM, 'name'),

            //bad name
            array('merchant', 'google6.com', 'name_more_than_20_symbols', 400, Response::ERR_BAD_PARAM, 'name'),

            //bad type
            array('anonym', 'google6.com', 'store_name', 400, Response::ERR_BAD_TYPE, null),

            //all ok - will create store
            array('merchant', 'google6.com', 'store_name', 200, null, null),

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

            $cur_store = MerchantStores::findFirst($url);

            //create order for new merchant

            /* //required fields (* - optional)
             * merchant_id,
             * amount,
             * currency (in iso - UAH, USD, etc.),
             * order_id (on merchant site),
             * server_url,
             * success_url,
             * fail_url,
             * signature,
             * details *
             */

            $store_id     = $cur_store->store_id;
            $amount       = 1;
            $currency     = 'UAH';
            $order_id     = 1;
            $server_url   = base64_decode($cur_store->url) . '/server';
            $success_url  = base64_decode($cur_store->url) . '/success';
            $fail_url     = base64_decode($cur_store->url) . '/fail';
            $details      = 'test details';

            $data = [
                'store_id' => $store_id,
                'amount' => number_format($amount, 2, '.', ''),
                'currency' => $currency,
                'order_id' => (string)$order_id,
                'details' => $details,
            ];
            ksort($data);
            $base64_data = base64_encode(json_encode($data));
            $verify_signature = base64_encode(hash('sha256', ($cur_store->secret_key . $base64_data)));

            $signature = $verify_signature;

            // Initialize Guzzle client
            $client = new Client();

            $user_data = $this->test_config['anonym'];
            $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

            //[TEST] create order -------------------

            // Create a POST request
            $response = $client->request(
                'POST',
                'http://' . $this->api_host .'/merchant/orders',
                [
                    'headers' => [
                        'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                    ],
                    'http_errors' => false,
                    'form_params' => [
                         "store_id" => $store_id,
                         "amount" => $amount,
                         "currency" => $currency,
                         "order_id" => $order_id,
                         "server_url" => $server_url,
                         "success_url" => $success_url,
                         "fail_url" => $fail_url,
                         "signature" => $signature,
                         "details" => $details,
                    ]
                ]
            );

            $real_http_code = $response->getStatusCode();
            $stream         = $response->getBody();
            $body           = $stream->getContents();
            $encode_data    = json_decode($body);

            $this->assertEquals(
                200,
                $real_http_code
            );

            $this->assertTrue(
                !empty($encode_data)
            );

            $this->assertInternalType('object', $encode_data);

            //test answer data structure
            $this->assertTrue(
                property_exists($encode_data, 'id')
            );

            $cur_order = MerchantOrders::findFirst($encode_data->id);

            //[TEST] get orders for store -------------------

            $user_data = $this->test_config['merchant'];
            $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://' . $this->api_host .'/merchant/stores/' . $cur_store->store_id . '/orders',
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
                200,
                $real_http_code
            );

            $this->assertTrue(
                property_exists($encode_data, 'items')
            );

            $this->assertInternalType('object', $encode_data);
            $this->assertInternalType('array',  $encode_data->items);

            //[TEST] get order by id -------------------

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://' . $this->api_host .'/merchant/orders/' . $cur_order->id,
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
                200,
                $real_http_code
            );

            $this->assertTrue(
                !empty($encode_data)
            );

            //test answer data structure
            $this->assertTrue(
                property_exists($encode_data, 'id')
            );

            $this->assertEquals(
                $cur_order->id,
                $encode_data->id
            );

            //clear test data

            //delete test order
            if ($cur_order) {
                $cur_order->delete();
            }

            //delete test store
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