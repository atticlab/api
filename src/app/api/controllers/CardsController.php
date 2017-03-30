<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Cards;
use App\Lib\Exception;
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
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
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

        return $this->response->json($card);
    }

    public function listAction()
    {
        $allowed_types = [
            Account::TYPE_DISTRIBUTION
        ];
        $requester = $this->request->getAccountId();
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $limit = intval($this->request->get('limit'))  ?? $this->config->riak->default_limit;
        $offset = intval($this->request->get('offset')) ?? 0;
        if (!is_integer($limit)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'limit');
        }
        if (!is_integer($offset)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'offset');
        }
        //get all cards for agent
        try {
            if (empty($requester)) {
                throw new Exception(Exception::EMPTY_PARAM, 'agent_id');
            }
            if (!Account::isValidAccountId($requester)) {
                throw new Exception(Exception::BAD_PARAM, 'agent_id');
            }
            $cards = Cards::findWithField('agent_id_s', $requester, $limit, $offset, 'created_date_i', 'desc');

            return $this->response->json($cards);
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
        if (!DEBUG_MODE && !$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }
        $tx = $this->payload->tx ?? null;
        $data = $this->payload->data ?? null;
        if (empty($tx)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'tx');
        }
        if (empty($data)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'data');
        }

        if (DEBUG_MODE) {
            return $this->response->json();
        }

        $data = json_decode($data);
        $client = new Client();
        //send tx to horizon
        $response = $client->request(
            'POST',
            $this->config->horizon->host . '/transactions',
            [
                'http_errors' => false,
                'form_params' => [
                    "tx" => $tx
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
        $next_link = $this->config->horizon->host . '/transactions/'
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
                if (empty($operation) || empty($operation->account) || $operation->funder != $requester) {
                    return $this->response->error(Response::ERR_SERVICE, 'Unexpected answer from horizon');
                }

                //get scratch card account info for take balance
                $scard_response = $client->request(
                    'GET',
                    $this->config->horizon->host . '/accounts/' . $operation->account,
                    [
                        'http_errors' => false
                    ]
                );
                if ($scard_response->getStatusCode() != 200) {
                    return $this->response->error(Response::ERR_TX, 'Can not get scratch card account from horizon');
                }

                $scard_body = json_decode($scard_response->getBody()->getContents());

                if ($scard_body->type != 'scratch_card') {
                    return $this->response->error(Response::ERR_TX, 'Unexpected account type');
                }

                foreach ($scard_body->balances as $balance) {
                    if ((float)$balance->balance > 0 ) {
                        try {
                            $card = new Cards($operation->account);
                        } catch (Exception $e) {
                            return $this->handleException($e->getCode(), $e->getMessage());
                        }

                        $card->created_date_i = time();
                        $card->used_date      = false;
                        $card->is_used_b      = false;

                        //TODO: get type of cards from frontend
                        $card->type_i     = 0; //0 - prepaid card, 1 - credit
                        $card->seed       = $data->{$operation->account};
                        $card->amount_f   = round($balance->balance, 2);
                        $card->asset_s    = $balance->asset_code;
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
            }

            $next_link = $body->_links->next->href;

        } while (!empty($next_operations));

        return $this->response->json();
    }
}