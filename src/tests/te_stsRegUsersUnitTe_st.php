<?php

namespace Regusers;

use \App\Models\Companies;
use App\Models\RegUsers;
use \Phalcon\DI;
use Smartmoney\Stellar\Account;
use GuzzleHttp\Client;
use App\Lib\Response;

/**
 * Class UnitTest
 */
class RegUsersUnitTest extends \UnitTestCase
{

    public static function CreateRegUserProvider()
    {

        $ind_code = rand(1, 999999);
        $requester_type = 'admin';
        $params = array($ind_code, 'EUAH', 'John', 'Smith', 'Harconnen', 'johnsmith@mail.com', '1106216',
            'nowhere', 'KL1109445');
        $errors = array();

        //fill errors with idential values for this test case
        foreach ($params as $key => $value) {
            $errors[] = array(400, Response::ERR_EMPTY_PARAM, $key);
        }

        $result_array = array();

        //fill result array
        $i = 0;
        foreach ($params as $key => $value) {
            $result_array[] = array_merge(array($requester_type), $params[$i], $errors[$i]);
            $i++;
        }

        return $result_array;
        /*array(

            //example: array (requester_type, ipn_code, asset, surname, name, middle_name, email, phone,
            //                address, passport, http_code, err_code, message)

            //no ipn_code
            array('admin', null, 'EUAH', 'John', 'Smith', 'Harconnen', 'johnsmith@mail.com', '1106216',
                   'nowhere', 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no asset
            array('admin', $ind_code, null, 'John', 'Smith', 'Harconnen', 'johnsmith@mail.com', '1106216',
                'nowhere', 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no surname
            array('admin', $ind_code, 'EUAH', null, 'Smith', 'Harconnen', 'johnsmith@mail.com', '1106216',
                'nowhere', 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no name
            array('admin', $ind_code, 'EUAH', 'John', null, 'Harconnen', 'johnsmith@mail.com', '1106216',
                'nowhere', 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no middle_name
            array('admin', $ind_code, 'EUAH', 'John', 'Smith', null, 'johnsmith@mail.com', '1106216',
                'nowhere', 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no email
            array('admin', $ind_code, 'EUAH', 'John', 'Smith', 'Harconnen', null, '1106216',
                'nowhere', 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no phone
            array('admin', $ind_code, 'EUAH', 'John', 'Smith', 'Harconnen', 'johnsmith@mail.com', null,
                'nowhere', 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no address
            array('admin', $ind_code, 'EUAH', 'John', 'Smith', 'Harconnen', 'johnsmith@mail.com', '1106216',
                null, 'KL1109445', 400, Response::ERR_EMPTY_PARAM, 'ind_code'),
            //no passport
            array('admin', $ind_code, 'EUAH', 'John', 'Smith', 'Harconnen', 'johnsmith@mail.com', '1106216',
                'nowhere', null, 400, Response::ERR_EMPTY_PARAM, 'ind_code'),

            //all ok - will create reguser - !!!MUST BE LAST IN ARRAY!!!
            array('admin', $ind_code, 'EUAH', 'John', 'Smith', 'Harconnen', 'johnsmith@mail.com', '1106216',
                'nowhere', 'KL1109445', 200, null, 'success')

        );*/

    }

    /**
     * @dataProvider CreateCompanyProvider
     */
    public function testCreateRegUser($requester_type, $ipn_code, $asset, $surname, $name,
                                      $middle_name, $email, $phone, $address, $passport, $http_code, $err_code, $msg)
    {

        parent::setUp();

        $client = new Client();

        //[TEST] create new reguser ------------------
        if (!empty($ipn_code) && RegUsers::isExist($ipn_code)) {

            do {
                //find free company code
                $ipn_code = rand(1, 999999);
            } while (RegUsers::isExist($ipn_code));

        }

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://' . $this->api_host . '/regusers',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "ipn_code" => $ipn_code,
                    "asset" => $asset,
                    "surname" => $surname,
                    "name" => $name,
                    "middle_name" => $middle_name,
                    "email" => $email,
                    "phone" => $phone,
                    "address" => $address,
                    "passport" => $passport
                ]
            ]
        );

        $real_http_code = $response->getStatusCode();
        $stream = $response->getBody();
        $body = $stream->getContents();
        $encode_data = json_decode($body);

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

            //[TEST] get early created company by codpassporte -------------------

            // Create a GET request
            $response = $client->request(
                'GET',
                'http://' . $this->api_host . '/regusers/' . $ipn_code,
                [
                    'headers' => [
                        'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                    ],
                    'http_errors' => false
                ]
            );

            $real_http_code = $response->getStatusCode();
            $stream = $response->getBody();
            $body = $stream->getContents();
            $encode_data = json_decode($body);

            $this->assertEquals(
                200,
                $real_http_code
            );

            $this->assertTrue(
                !empty($encode_data)
            );

            //test answer data structure
            $this->assertTrue(
                property_exists($encode_data, 'ipn_code')
            );

            $this->assertInternalType('object', $encode_data);

            //delete test company
            $cur_company = Companies::get($ipn_code);
            if ($cur_company) {
                $cur_company->delete();
            }

        }

    }

    public static function GetReUsersProvider()
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
    public function testGetRegusers($requester_type, $http_code, $err_code)
    {

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config[$requester_type];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //[TEST] get all companies -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host . '/companies',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false
            ]
        );

        $real_http_code = $response->getStatusCode();
        $stream = $response->getBody();
        $body = $stream->getContents();
        $encode_data = json_decode($body);

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
            $this->assertInternalType('array', $encode_data->items);

        }

    }

    public function testGetNotExistRegUser()
    {

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
            'http://192.168.1.155:8180/companies/' . $code,
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false
            ]
        );

        $stream = $response->getBody();
        $body = $stream->getContents();
        $encode_data = json_decode($body);

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