<?php

namespace Cards;

use \App\Models\Cards;
use \Phalcon\DI;
use Smartmoney\Stellar\Account;
use GuzzleHttp\Client;
use App\Lib\Response;

/**
 * Class UnitTest
 */
class CardsUnitTest extends \UnitTestCase
{

    public static function CreateCardProvider()
    {

        $crypt_seed = 'crypt_seed';
        $account_id = 'GDXJJRGKHDIH3HQW5OMVUXXAR25EBX6GRDU5XVPEAWH2WOZYQ2ZHXI35'; //zanonym4/123123

        return array(

            //example: array (requester_type, account_id, seed, type, amount, asset, http_code, err_code, message)

            //no account_id
            array('agent', null, $crypt_seed, 0, 100, 'EUAH', 400, Response::ERR_EMPTY_PARAM, 'account_id'),

            //no seed
            array('agent', $account_id, null, 0, 100, 'EUAH', 400, Response::ERR_EMPTY_PARAM, 'seed'),

            //no amount
            array('agent', $account_id, $crypt_seed, 0, null, 'EUAH', 400, Response::ERR_EMPTY_PARAM, 'amount'),

            //bad amount
            array('agent', $account_id, $crypt_seed, 0, -100, 'EUAH', 400, Response::ERR_BAD_PARAM, 'amount'),

            //no asset
            array('agent', $account_id, $crypt_seed, 0, 100, null, 400, Response::ERR_EMPTY_PARAM, 'asset'),

            //no type
            array('agent', $account_id, $crypt_seed, null, 100, 'EUAH', 400, Response::ERR_EMPTY_PARAM, 'type'),

            //bad type
            array('agent', $account_id, $crypt_seed, 666, 100, 'EUAH', 400, Response::ERR_BAD_PARAM, 'type'),

            //bad requester account type
            array('anonym', $account_id, $crypt_seed, 0, 100, 'EUAH', 400, Response::ERR_BAD_TYPE, null),

            //all ok - will create card
            array('agent', $account_id, $crypt_seed, 0, 100, 'EUAH', 200, null, 'success'),

        );

    }

    /**
     * @dataProvider CreateCardProvider
     */
    public function testCreateCard($requester_type, $account_id, $seed, $type, $amount, $asset, $http_code, $err_code, $msg)
    {

        parent::setUp();

        $client = new Client();

        //[TEST] create new card ------------------

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://' . $this->api_host .'/cards',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "account_id" => $account_id,
                    "seed"       => $seed,
                    "amount"     => $amount,
                    "asset"      => $asset,
                    "type"       => $type
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

        //when we make test that success create card
        if ($real_http_code == 200) {

            //[TEST] get early created card by account_id -------------------

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://' . $this->api_host .'/cards/' . $account_id,
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

            $this->assertInternalType('object', $encode_data);

            $this->assertTrue(
                property_exists($encode_data, 'account_id')
            );

            $this->assertEquals(
                $account_id,
                $encode_data->account_id
            );

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://' . $this->api_host .'/cards',
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

            $this->assertTrue(
                property_exists($encode_data, 'items')
            );

            $this->assertInternalType('object', $encode_data);
            $this->assertInternalType('array',  $encode_data->items);

            $this->assertTrue(
                count($encode_data->items) > 0
            );

            //delete test card
            $cur_card = Cards::findFirst($encode_data->account_id);

            if ($cur_card) {
                $cur_card->delete();
            }

        }

    }

    public static function GetCardsProvider()
    {

        return array(

            //example: array (requester_type, http_code, err_code)

            //bad account type
            array('anonym', 400, Response::ERR_BAD_TYPE),

            //all ok - will get list of companies
            array('agent', 200, null),

        );

    }

    /**
     * @dataProvider GetCardsProvider
     */
    public function testGetCards($requester_type, $http_code, $err_code){

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //[TEST] get all cards -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host .'/cards',
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