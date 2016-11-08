<?php

namespace Agents;

use \App\Models\Companies;
use App\Models\Agents;
use App\Models\Enrollments;
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

        // need create test company before create agent
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
                    "email"     => 'for0work0@gmail.com',
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

        //get enrollment id for emulate accept from agent and final approve from admin

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host .'/agents',
            [
                'query' => ['company_code' => $cmp_code],
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

        $agents = $encode_data->items;

        $this->assertEquals(
            1,
            count($agents)
        );

        $agent = $agents[0];

        $this->assertTrue(
            property_exists($agent, 'id')
        );

        $this->assertTrue(
            !empty($agent)
        );

        $this->assertEquals(
            Agents::TYPE_MERCHANT,
            $agent->type
        );

        // Create a GET request
        $response = $client->request(
            'GET',
            'http://' . $this->api_host .'/enrollments',
            [
                'query' => ['type' => 'agent'],
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

        $enrollments = $encode_data->items;

        $this->assertTrue(
            !empty($enrollments)
        );

        $last_enrollment = null;

        foreach ($enrollments as $enrollment) {
            if ($enrollment->target_id == $agent->id) {
                $last_enrollment = $enrollment;
            }
        }

        $this->assertTrue(
            !empty($last_enrollment)
        );

        $this->assertTrue(
            property_exists($last_enrollment, 'type')
        );

        $this->assertEquals(
            Enrollments::TYPE_AGENT,
            $last_enrollment->type
        );

        $this->assertEquals(
            Enrollments::STAGE_CREATED,
            $last_enrollment->stage
        );

        $this->assertTrue(
            property_exists($last_enrollment, 'id')
        );

        //[TEST] accept agent enrollment ------------------

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://' . $this->api_host . '/enrollments/accept/' . $last_enrollment->id,
            [
                'http_errors' => false,
                'form_params' => [
                    "token" => $last_enrollment->otp,
                    "account_id" => 'GDMAIWXEOBXARXUMBMNW3WTENXZR5TWMJIZHIWQEO22CZVCOS5SHPI6X',
                    "tx_trust" => 'test',
                    "login" => 'test'
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

        //check enrollment data after accept

        $enrollment_after = Enrollments::getDataByID($last_enrollment->id);

        $this->assertTrue(
            !empty($enrollment_after)
        );

        $this->assertEquals(
            Enrollments::STAGE_APPROVED,
            $enrollment_after->stage
        );

        //admin final approve of enrollment

        // Create a POST request
        $response = $client->request(
            'POST',
            'http://' . $this->api_host . '/enrollments/approve/' . $enrollment_after->id,
            [
                'headers' => [
                    'Signed-Nonce' => $this->generateAuthSignature($user_data['secret_key'])
                ],
                'http_errors' => false,
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

        //check enrollment data after admin approve

        $enrollment_approve = Enrollments::getDataByID($enrollment_after->id);

        $this->assertTrue(
            !empty($enrollment_approve)
        );

        $this->assertEquals(
            'GDMAIWXEOBXARXUMBMNW3WTENXZR5TWMJIZHIWQEO22CZVCOS5SHPI6X',
            $enrollment_approve->account_id
        );

        $this->assertEquals(
            'test',
            $enrollment_approve->login
        );

        $this->assertEquals(
            'test',
            $enrollment_approve->tx_trust
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

        $enrollment = Enrollments::findFirst($enrollment_after->id);

        if ($enrollment) {
            $enrollment->delete();
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