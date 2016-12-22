<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Cards;
use App\Lib\Exception;
use App\Services\Helpers;
use GuzzleHttp\Client;
use Smartmoney\Stellar\Account;

class CardsController extends ControllerBase
{

    public function getAction($account_id)
    {
        $allowed_types = [
            Account::TYPE_DISTRIBUTION
        ];
        $requester = $this->request->getAccountId();
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }
        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }
        $card = Cards::getAgentCard($account_id, $requester);
        if (empty($card)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'card');
        }
        return $this->response->single(Helpers::clearYzSuffixes($card));
    }

    public function listAction()
    {
        $allowed_types = [
            Account::TYPE_DISTRIBUTION
        ];
        $requester = $this->request->getAccountId();
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $limit  = $this->request->get('limit')  ?? $this->config->riak->default_limit;
        $offset = $this->request->get('offset') ?? 0;
        //get all cards for agent
        try {
            if (empty($requester)) {
                throw new Exception(Exception::EMPTY_PARAM, 'agent_id');
            }
            if (!Account::isValidAccountId($requester)) {
                throw new Exception(Exception::BAD_PARAM, 'agent_id');
            }
            $cards = Cards::findWithField('agent_id_s', $requester, $limit, $offset);
            return $this->response->items(Helpers::clearYzSuffixes($cards));
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function createCardsAction()
    {
        $allowed_types = [
            Account::TYPE_DISTRIBUTION
        ];
        $requester = $this->request->getAccountId();
        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $tx   = $this->payload->tx   ?? null;
        $data = $this->payload->data ?? null;
        if (empty($tx)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'tx');
        }
        if (empty($data)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'data');
        }
        $data = json_decode($data);
        $client = new Client();
        //send tx to horizon
        $response = $client->request(
            'POST',
            'http://' . $this->config->horizon->host . ':' . $this->config->horizon->port . '/transactions',
            [
                'http_errors' => false,
                'form_params' => [
                    "tx"      => $tx
                ]
            ]
        );
        if ($response->getStatusCode() != 200) {
            $this->logger->error($response->getBody()->getContents());
            return $this->response->error(Response::ERR_TX, 'Can not submit transaction');
        }
        $body = json_decode($response->getBody()->getContents());
        if (empty($body) || empty($body->hash)) {
            $this->logger->error($body);
            return $this->response->error(Response::ERR_TX, 'Bad horizon response');
        }

        $next_operations = [];
        $next_link = 'http://' . $this->config->horizon->host . ':' . $this->config->horizon->port .'/transactions/'
            . $body->hash . '/operations';

        //get operations by transaction hash
        do {
            $response = $client->request(
                'GET',
                $next_link,
                [
                    'http_errors' => false
                ]
            );
            if ($response->getStatusCode() != 200) {
                return $this->response->error(Response::ERR_TX, 'Can not get operations from horizon');
            }
            $body = json_decode($response->getBody()->getContents());
            $next_operations = $body->_embedded->records;
            foreach ($next_operations as $operation) {
                if (empty($operation) || empty($operation->from) || empty($operation->to) || empty($operation->amount) || empty($operation->asset_code)) {
                    return $this->response->error(Response::ERR_SERVICE, 'Unexpected answer from horizon');
                }
                if ($operation->from != $requester) {
                    return $this->response->error(Response::ERR_BAD_PARAM, 'Operation source account');
                }
                if (property_exists($data, $operation->to)) {

                    try {
                        $card = new Cards($operation->to);
                    } catch (Exception $e) {
                        return $this->handleException($e->getCode(), $e->getMessage());
                    }

                    $card->created_date_i = time();
                    $card->used_date      = false;
                    $card->is_used_b      = false;

                    //TODO: get type of cards from frontend
                    $card->type_i     = 0; //0 - prepaid card, 1 - credit
                    $card->seed       = $data->{$operation->to};
                    $card->amount_f   = $operation->amount;
                    $card->asset_s    = $operation->asset_code;
                    $card->agent_id_s = $requester;

                    try {
                        if (!$card->create()) {
                            $this->logger->emergency('Riak error while creating card');
                            throw new Exception(Exception::SERVICE_ERROR);
                        }
                    } catch (Exception $e) {
                        return $this->handleException($e->getCode(), $e->getMessage());
                    }
                }
            }

            $next_link = $body->_links->next->href;

        } while (!empty($next_operations));

        return $this->response->success();
    }

}