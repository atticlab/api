<?php

namespace Companies;

use \App\Models\Companies;
use \Phalcon\DI;

use GuzzleHttp\Client;
use App\Lib\Response;

/**
 * Class UnitTest
 */
class AgentsUnitTest extends \UnitTestCase
{

    public static function CreateCompanyProvider()
    {

        $code = 'test_company_' . rand(1, 999);

        return array(

            //example: array (code, title, address, email, phone, http_code, err_code, message)

            //no code
            array(null, 'test_company_title', 'test_company_address',
                'test_company@email.com', '1234567890', Response::$_http_codes[Response::ERR_EMPTY_PARAM], Response::ERR_EMPTY_PARAM, 'code'),

            //no title
            array($code, null, 'test_company_address',
                'test_company@email.com', '1234567890', Response::$_http_codes[Response::ERR_EMPTY_PARAM], Response::ERR_EMPTY_PARAM, 'title'),

            //no address
            array($code, 'test_company_title', null,
                'test_company@email.com', '1234567890', Response::$_http_codes[Response::ERR_EMPTY_PARAM], Response::ERR_EMPTY_PARAM, 'address'),

            //no email
            array($code, 'test_company_title', 'test_company_address',
                null, '1234567890', Response::$_http_codes[Response::ERR_EMPTY_PARAM], Response::ERR_EMPTY_PARAM, 'email'),

            //no phone
            array($code, 'test_company_title', 'test_company_address',
                'test_company@email.com', null, Response::$_http_codes[Response::ERR_EMPTY_PARAM], Response::ERR_EMPTY_PARAM, 'phone'),

            //all ok - will create company - !!!MUST BE LAST IN ARRAY!!!
            array($code, 'test_company_title', 'test_company_address',
                'test_company@email.com', '1234567890', 200, null, 'success'),

        );

    }

    /**
     * @dataProvider CreateCompanyProvider
     */
    public function testCreateCompany($code, $title, $address, $email, $phone, $http_code, $err_code, $msg)
    {

        parent::setUp();

        // Initialize Guzzle client
        $client = new Client();

        //[TEST] create new company ------------------
        if (!empty($code) && Companies::isExist($this->di['riak'], $code)) {

            do {
                //find free company code
                $code = 'test_company_' . rand(1, 999);
            } while (Companies::isExist($this->di['riak'], $code));

        }

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://192.168.1.155:8180/companies',
            [
                'http_errors' => false,
                'form_params' => [
                    "code"      => $code,
                    "title"     => $title,
                    "address"   => $address,
                    "email"     => $email,
                    "phone"     => $phone
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

        //when we make test that success create company
        if($real_http_code == 200) {

            //test success data
            $this->assertEquals(
                $encode_data->message,
                $msg
            );

            //[TEST] get early created company by code -------------------

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://192.168.1.155:8180/companies',
                [
                    'http_errors' => false,
                    'query' => ['code' => $code]
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
            $this->assertInternalType('array',  $encode_data->items);

            $this->assertEquals(
                1,
                count($encode_data)
            );
        }

    }

    public function testGetCompanies(){

        // Initialize Guzzle client
        $client = new Client();

        //[TEST] get all companies -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://192.168.1.155:8180/companies',
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
        $this->assertInternalType('array',  $encode_data->items);

    }

    public function testGetNotExistCompany(){

        // Initialize Guzzle client
        $client = new Client();

        do {
            //find free company code
            $code = 'test_company_' . rand(1, 999);
        } while (Companies::isExist($this->di['riak'], $code));

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://192.168.1.155:8180/companies',
            [
                'http_errors' => false,
                'query' => [
                    'code' => $code
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
            Response::$_http_codes[Response::ERR_NOT_FOUND]
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