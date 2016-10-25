<?php

namespace Invoice;

use App\Models\InvoiceBans;
use App\Models\Invoices;
use \Phalcon\DI;
use Smartmoney\Stellar\Account;

use GuzzleHttp\Client;
use App\Lib\Response;

/**
 * Class UnitTest
 */
class InvoiceUnitTest extends \UnitTestCase
{

    public static function CreateInvoiceProvider()
    {

        return array(

            //example: array (requester_type, asset, amount, memo, http_code, err_code, message)

            //no asset
            array('anonym', null, 123.45, null, 400, Response::ERR_EMPTY_PARAM, 'asset'),

            //no amount
            array('anonym', 'EUAH', null, null, 400, Response::ERR_EMPTY_PARAM, 'amount'),

            //bad amount
            array('anonym', 'EUAH', -100, null, 400, Response::ERR_BAD_PARAM, 'amount'),

            //bad memo (length > 14)
            array('anonym', 'EUAH', 123.45, 'text_more_than_14_symbols', 400, Response::ERR_BAD_PARAM, 'memo'),

            //not allowed account type
            array('admin', 'EUAH', 123.45, null, 400, Response::ERR_BAD_TYPE, null),

            //all ok - will create invoice without memo - !!!MUST BE LAST IN ARRAY!!!
            array('anonym', 'EUAH', 123.45, null, 200, null, null),

            //all ok - will create invoice with memo - !!!MUST BE LAST IN ARRAY!!!
            array('anonym', 'EUAH', 123.45, 'normal_memo', 200, null, null),

        );

    }

    /**
     * @dataProvider CreateInvoiceProvider
     */
    public function testCreateInvoice($requester_type, $asset, $amount, $memo, $http_code, $err_code, $msg)
    {

        parent::setUp();

        $client = new Client();

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://192.168.1.155:8180/invoice',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "asset"  => $asset,
                    "amount" => $amount,
                    "memo"   => $memo
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

        if ($err_code) {
            //test error code
            $this->assertEquals(
                $err_code,
                $encode_data->error
            );
        }

        //test message
        if ($msg) {
            $this->assertEquals(
                $msg,
                $encode_data->message
            );
        }

        //when we make test that success create invoice
        if ($real_http_code == 200) {

            //test success message - invoice code
            $this->assertInternalType('object', $encode_data);
            $this->assertInternalType('string', $encode_data->id);

            $id = $encode_data->id;

            //[TEST] get early created invoice by id -------------------

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://192.168.1.155:8180/invoice',
                [
                    'headers' => [
                        'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                    ],
                    'http_errors' => false,
                    'query' => ['id' => $id]
                ]
            );

            $stream         = $response->getBody();
            $body           = $stream->getContents();
            $encode_data    = json_decode($body);

            $this->assertInternalType('object', $encode_data);
            $this->assertInternalType('string', $encode_data->id);

            //check with empty memo
            if (empty($memo)) {
                $this->assertTrue(
                    empty($encode_data->memo)
                );
            } else {
                $this->assertFalse(
                    empty($encode_data->memo)
                );
            }

            //delete test invoice
            $cur_invoice = Invoices::get($id);
            if ($cur_invoice) {
                $cur_invoice->delete();
            }

        }

    }

    public function testGetInvoices(){

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config['anonym'];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //[TEST] get all invoices -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://192.168.1.155:8180/invoice',
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

        $this->assertInternalType('object', $encode_data);
        $this->assertInternalType('array', $encode_data->items);

    }

    public function testGetNotExistInvoice()
    {

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config['anonym'];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //get free invoice id
        $id = Invoices::generateUniqueId();

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://192.168.1.155:8180/invoice',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'query' => ['id' => $id]
            ]
        );

        $stream         = $response->getBody();
        $body           = $stream->getContents();
        $encode_data    = json_decode($body);

        //test error code

        $this->assertEquals(
            Response::ERR_NOT_FOUND,
            $encode_data->error
        );


    }

    public static function BanAccountProvider()
    {

        $test_acc_id = 'GDWWTT7NBH52BAAFHIQR45IRPFYQSKSKU4NIFJ5DHWG3IGVZ7KMAV4U4';

        return array(

            //example: array (requester_type, accountId, seconds, http_code, err_code, message)

            //no accountId
            array('admin', null, 600, 400, Response::ERR_EMPTY_PARAM, 'accountId'),

            //bad accountId
            array('admin', 'bad_account_id', 600, 400, Response::ERR_BAD_PARAM, 'accountId'),

            //no seconds
            array('admin', $test_acc_id, null, 400, Response::ERR_EMPTY_PARAM, 'seconds'),

            //bad seconds
            array('admin', $test_acc_id, -100, 400, Response::ERR_BAD_PARAM, 'seconds'),

            //not allowed account type
            array('anonym', $test_acc_id, 600, 400, Response::ERR_BAD_TYPE, null),

            //all ok - will ban account
            array('admin', $test_acc_id, 600, 200, null, 'success')

        );

    }

    /**
     * @dataProvider BanAccountProvider
     */
    public function testBanAccount($requester_type, $accountId, $seconds, $http_code, $err_code, $msg)
    {

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        // Add ban
        $response = $client->request(
            'POST',
            'http://192.168.1.155:8180/invoice/bans',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "accountId" => $accountId,
                    "seconds"   => $seconds
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

        if ($err_code) {
            //test error code
            $this->assertEquals(
                $err_code,
                $encode_data->error
            );
        }


        //test message
        if ($msg) {
            $this->assertEquals(
                $msg,
                $encode_data->message
            );
        }

        //when we make test that success ban account
        if ($real_http_code == 200) {

            //delete test ban
            $cur_ban = InvoiceBans::get($accountId);
            if ($cur_ban) {
                $cur_ban->delete();
            }

        }

    }

    public function testGetBanAccounts(){

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config['admin'];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //[TEST] get all bans -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://192.168.1.155:8180/invoice/bans',
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

        $this->assertInternalType('object', $encode_data);
        $this->assertInternalType('array', $encode_data->items);

    }

}