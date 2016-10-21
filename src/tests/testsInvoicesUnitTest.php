<?php

namespace Companies;

use \App\Models\Invoices;
use \Phalcon\DI;

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

            //example: array (asset, amount, memo, http_code, err_code, message)

            //no asset
            array(null, 123.45, null, Response::$_http_codes[Response::ERR_EMPTY_PARAM], Response::ERR_EMPTY_PARAM, 'asset'),

            //no amount
            array('EUAH', null, null, Response::$_http_codes[Response::ERR_EMPTY_PARAM], Response::ERR_EMPTY_PARAM, 'amount'),

            //bad amount
            array('EUAH', -100, null, Response::$_http_codes[Response::ERR_BAD_PARAM], Response::ERR_BAD_PARAM, 'amount'),

            //bad memo (length > 14)
            array('EUAH', 123.45, 'text_more_than_14_symbols', Response::$_http_codes[Response::ERR_BAD_PARAM], Response::ERR_BAD_PARAM, 'memo'),

            //all ok - will create invoice without memo - !!!MUST BE LAST IN ARRAY!!!
            array('EUAH', 123.45, null, 200, null, null),

            //all ok - will create invoice with memo - !!!MUST BE LAST IN ARRAY!!!
            array('EUAH', 123.45, 'normal_memo', 200, null, null),

        );

    }

    /**
     * @dataProvider CreateInvoiceProvider
     */
    public function testCreateInvoice($asset, $amount, $memo, $http_code, $err_code, $msg)
    {

        parent::setUp();

        // Initialize Guzzle client
        $client = new Client();

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://192.168.1.155:8180/invoice',
            [
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
            $real_http_code,
            $http_code
        );

        //test error code
        if (!empty($encode_data->code)) {
            $this->assertEquals(
                $encode_data->code,
                $err_code
            );
        }

        //test error message
        if (!empty($encode_data->message)) {
            $this->assertEquals(
                $encode_data->message,
                $msg
            );
        }

        //when we make test that success create invoice
        if($real_http_code == 200) {

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
                    'http_errors' => false,
                    'query' => ['id' => $id]
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
            $this->assertInternalType('string', $encode_data->id);

            //check with empty memo
            if (empty($memo)) {
                $this->assertTrue(
                    empty($encode_data->data->memo)
                );
            } else {
                $this->assertFalse(
                    empty($encode_data->data->memo)
                );
            }
        }

    }

    public function testGetInvoices(){

        // Initialize Guzzle client
        $client = new Client();

        //[TEST] get all invoices -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://192.168.1.155:8180/invoice',
            [
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

    public function testGetNotExistInvoice(){

        // Initialize Guzzle client
        $client = new Client();

        //get free invoice id
        $id = Invoices::generateUniqueId($this->di['riak']);

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://192.168.1.155:8180/invoice',
            [
                'http_errors' => false,
                'query' => ['id' => $id]
            ]
        );

        $real_http_code = $response->getStatusCode();
        $stream         = $response->getBody();
        $body           = $stream->getContents();
        $encode_data    = json_decode($body);

        //test http code
        $this->assertEquals(
            Response::$_http_codes[Response::ERR_NOT_FOUND],
            $real_http_code
        );

        //test error code
        if (!empty($encode_data->code)) {
            $this->assertEquals(
                $encode_data->code,
                Response::ERR_NOT_FOUND
            );
        }

    }
}