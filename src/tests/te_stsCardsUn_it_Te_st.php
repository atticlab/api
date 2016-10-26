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

        $test_seed = '';

        return array(

            //example: array (requester_type, seed, amount, asset, http_code, err_code, message)

            //no seed
            array('agent', null, 100, 'EUAH', 400, Response::ERR_EMPTY_PARAM, 'seed'),

            //bad seed
            array('agent', 'bad_seed', 100, 'EUAH', 400, Response::ERR_BAD_PARAM, 'seed'),

            //no amount
            array('agent', $test_seed, null, 'EUAH', 400, Response::ERR_EMPTY_PARAM, 'amount'),

            //bad amount
            array('agent', $test_seed, -100, 'EUAH', 400, Response::ERR_BAD_PARAM, 'amount'),

            //no asset
            array('agent', $test_seed, 100, null, 400, Response::ERR_EMPTY_PARAM, 'asset'),

            //bad requester account type
            array('anonym', $test_seed, 100, 'EUAH', 400, Response::ERR_BAD_TYPE, null),

            //all ok - will create card
            array('agent', $test_seed, 100, 'EUAH', 200, null, null),

        );

    }

    /**
     * @dataProvider CreateCardProvider
     */
    public function testCreateCard($requester_type, $seed, $amount, $asset, $http_code, $err_code, $msg)
    {

        parent::setUp();

        $client = new Client();

        //[TEST] create new card ------------------

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://192.168.1.155:8180/cards',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "seed"      => $seed,
                    "amount"    => $amount,
                    "asset"     => $asset
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

        //test error code
        if ($err_code) {
            $this->assertEquals(
                $err_code,
                $encode_data->error
            );
        }

        //check ERROR message (in case of success - answer will return with different structure
        if ($real_http_code != 200) {
            //test message
            if ($msg) {
                $this->assertEquals(
                    $msg,
                    $encode_data->message
                );
            }
        }

        //when we make test that success create card
        if ($real_http_code == 200) {

            $this->assertTrue(
                !empty($encode_data->card_id)
            );

            //[TEST] get early created card by card_id -------------------

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://192.168.1.155:8180/cards',
                [
                    'headers' => [
                        'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                    ],
                    'http_errors' => false,
                    'query' => ['card_id' => $encode_data->card_id]
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

            $this->assertInternalType('object', $encode_data);

            $this->assertTrue(
                !empty($encode_data->card_id)
            );

            //delete test company
            $cur_card = Cards::get($encode_data->card_id);
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
            'http://192.168.1.155:8180/cards',
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

        //test error code
        if ($err_code) {
            $this->assertEquals(
                $err_code,
                $encode_data->error
            );
        }

        if ($real_http_code == 200) {

            $this->assertInternalType('object', $encode_data);
            $this->assertInternalType('array',  $encode_data->items);

        }

    }

}