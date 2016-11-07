<?php

namespace Agents;

use \App\Models\Companies;
use App\Models\Agents;
use \Phalcon\DI;
use Smartmoney\Stellar\Account;
use GuzzleHttp\Client;
use App\Lib\Response;

/**
 * Class UnitTest
 */
class AgentsUnitTest extends \UnitTestCase
{

    public function testCreateAgent()
    {

        parent::setUp();

        $client = new Client();

        $user_data = $this->test_config['admin'];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        do {
            //find free company code
            $cmp_code = 'test_company_' . rand(1, 999);
        } while (Companies::isExist($cmp_code));

        // need create test company at first
        $response = $client->request(
            'POST',
            'http://' . $this->api_host .'/companies',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "code"      => $cmp_code,
                    "title"     => 'test_data',
                    "address"   => 'test_data',
                    "email"     => 'test_data@test.com',
                    "phone"     => '123123123'
                ]
            ]
        );

        $real_http_code = $response->getStatusCode();
        $stream         = $response->getBody();
        $body           = $stream->getContents();
        $encode_data    = json_decode($body);

        //test http code
        $this->assertEquals(
            200,
            $real_http_code
        );

        $this->assertTrue(
            !empty($encode_data)
        );

        //test success data structure
        $this->assertTrue(
            property_exists($encode_data, 'message')
        );

        $this->assertEquals(
            'success',
            $encode_data->message
        );

        //[TEST] create new agent ------------------

        do {
            //find free agent id
            $id = Agents::generateID();
        } while (Agents::isExist($id));

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://' . $this->api_host . '/agents',
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
                'form_params' => [
                    "type" => Agents::TYPE_MERCHANT,
                    "asset" => 'EUAH',
                    "company_code" => $cmp_code
                ]
            ]
        );

        $real_http_code = $response->getStatusCode();
        $stream = $response->getBody();
        $body = $stream->getContents();
        $encode_data = json_decode($body);

        //test http code
        $this->assertEquals(
            200,
            $real_http_code
        );

        $this->assertTrue(
            !empty($encode_data)
        );

        //test success data structure
        $this->assertTrue(
            property_exists($encode_data, 'message')
        );

        $this->assertEquals(
            'success',
            $encode_data->message
        );

        //clear test data
        $agent_data = Agents::isExistByIndex('cmp_code', $cmp_code);

        if (!empty($agent_data) && !empty($agent_data[0])){

            $agent = Agents::findFirst($agent_data[0]);

            if ($agent) {
                $agent->delete();
            }

        }

        $company = Companies::findFirst($cmp_code);

        if ($company) {
            $company->delete();
        }

    }

    public function testGetAgents()
    {

        // Initialize Guzzle client
        $client = new Client();

        $user_data = $this->test_config['admin'];
        $user_data['secret_key'] = Account::decodeCheck('seed', $user_data['seed']);

        //[TEST] get all regusers -------------------

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host . '/agents',
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

        $this->assertTrue(
            property_exists($encode_data, 'items')
        );

        $this->assertInternalType('object', $encode_data);
        $this->assertInternalType('array', $encode_data->items);

    }

}