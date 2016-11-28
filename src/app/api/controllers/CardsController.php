<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Cards;
use App\Lib\Exception;
use Smartmoney\Stellar\Account;


class CardsController extends ControllerBase
{

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
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

}