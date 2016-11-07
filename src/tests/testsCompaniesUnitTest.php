<?php

namespace Companies;

use \App\Models\Companies;
use \Phalcon\DI;
use Smartmoney\Stellar\Account;
use GuzzleHttp\Client;
use App\Lib\Response;

/**
 * Class UnitTest
 */
class CompaniesUnitTest extends \UnitTestCase
{

    public static function CreateCompanyProvider()
    {

        $code = 'test_company_' . rand(1, 999);

        return array(

            //example: array (requester_type, code, title, address, email, phone, http_code, err_code, message)

            //no code
            array('admin', null, 'test_company_title', 'test_company_address',
                'test_company@email.com', '1234567890', 400, Response::ERR_EMPTY_PARAM, 'code'),

            //no title
            array('admin', $code, null, 'test_company_address',
                'test_company@email.com', '1234567890', 400, Response::ERR_EMPTY_PARAM, 'title'),

            //no address
            array('admin', $code, 'test_company_title', null,
                'test_company@email.com', '1234567890', 400, Response::ERR_EMPTY_PARAM, 'address'),

            //no email
            array('admin', $code, 'test_company_title', 'test_company_address',
                null, '1234567890', 400, Response::ERR_EMPTY_PARAM, 'email'),

            //no phone
            array('admin', $code, 'test_company_title', 'test_company_address',
                'test_company@email.com', null, 400, Response::ERR_EMPTY_PARAM, 'phone'),

            //bad account type
            array('anonym', $code, 'test_company_title', 'test_company_address',
                'test_company@email.com', '1234567890', 400, Response::ERR_BAD_TYPE, null),

            //all ok - will create company - !!!MUST BE LAST IN ARRAY!!!
            array('admin', $code, 'test_company_title', 'test_company_address',
                'test_company@email.com', '1234567890', 200, null, 'success'),

        );

    }

    /**
     * @dataProvider CreateCompanyProvider
     */
    public function testCreateCompany($requester_type, $code, $title, $address, $email, $phone, $http_code, $err_code, $msg)
    {

        parent::setUp();

        $client = new Client();

        //[TEST] create new company ------------------

        if (!empty($code) && Companies::isExist($code)) {

            do {
                //find free company code
                $code = 'test_company_' . rand(1, 999);
            } while (Companies::isExist($code));

        }

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://' . $this->api_host .'/companies',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
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

        //when we make test that success create company
        if ($real_http_code == 200) {

            //[TEST] get early created company by code -------------------

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://' . $this->api_host .'/companies/' . $code,
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
                property_exists($encode_data, 'code')
            );

            $this->assertInternalType('object', $encode_data);

            //delete test company
            $cur_company = Companies::findFirst($code);
            if ($cur_company) {
                $cur_company->delete();
            }

        }

    }

    public static function GetCompaniesProvider()
    {

        return array(

            //example: array (requester_type, http_code, err_code)

            //bad account type
            array('anonym', 400, Response::ERR_BAD_TYPE),

            //all ok - will get list of companies
            array('admin', 200, null),

        );

    }

    /**
     * @dataProvider GetCompaniesProvider
     */
    public function testGetCompanies($requester_type, $http_code, $err_code){

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //[TEST] get all companies -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host .'/companies',
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

        if ($real_http_code == 200) {

            $this->assertTrue(
                property_exists($encode_data, 'items')
            );

            $this->assertInternalType('object', $encode_data);
            $this->assertInternalType('array',  $encode_data->items);

        }

    }

    public function testGetNotExistCompany(){

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config['admin'];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        do {
            //find free company code
            $code = 'test_company_' . rand(1, 999);
        } while (Companies::isExist($code));

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host .'/companies/' . $code,
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false
            ]
        );

        $stream         = $response->getBody();
        $body           = $stream->getContents();
        $encode_data    = json_decode($body);

        $this->assertTrue(
            !empty($encode_data)
        );

        //test error data structure
        $this->assertTrue(
            property_exists($encode_data, 'error')
        );

        //test error code
        $this->assertEquals(
            Response::ERR_NOT_FOUND,
            $encode_data->error
        );

    }
}