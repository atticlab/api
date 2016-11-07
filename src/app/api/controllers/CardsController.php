<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Cards;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;


class CardsController extends ControllerBase
{

    public function createAction()
    {
        $allowed_types = [
            Account::TYPE_DISTRIBUTION
        ];

        $requester = $this->request->getAccountId();

        if (!$this->isAllowedType($requester, $allowed_types)) {
            return $this->response->error(Response::ERR_BAD_TYPE);
        }

        $account_id = $this->payload->account_id ?? null;

        if (empty($account_id)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'account_id');
        }

        if (!Account::isValidAccountId($account_id)) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'account_id');
        }

        try {
            $card = new Cards($account_id);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

        $card->created_date     = time();
        $card->used_date        = false;
        $card->is_used          = false;

        $card->type     = isset($this->payload->type)   ? $this->payload->type : null;
        $card->seed     = $this->payload->seed          ?? null;
        $card->amount   = $this->payload->amount        ?? null;
        $card->asset    = $this->payload->asset         ?? null;
        $card->agent_id = $requester;

        try {

            if ($card->create()) {
                return $this->response->single(['message' => 'success']);
            }

            $this->logger->emergency('Riak error while creating card');
            throw new Exception(Exception::SERVICE_ERROR);

        } catch (Exception $e) {

            $this->handleException($e->getCode(), $e->getMessage());

        }
    }

    public function getAction($account_id)
    {
        //get card by account_id

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

        return $this->response->single($card);
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

        $limit  = $this->request->get('limit')   ?? null;
        $offset = $this->request->get('offset')  ?? null;

        //get all cards for agent
        try {
            $cards = Cards::findAgentCards($requester, $limit, $offset);
            return $this->response->items($cards);
        } catch (Exception $e) {
            $this->handleException($e->getCode(), $e->getMessage());
        }

    }

}