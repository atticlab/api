<?php

namespace App\lib;
use Smartmoney\Stellar\Account;

class Request extends \Phalcon\Http\Request
{
    const SIGN_HEADER = 'Signature';

    /**
     * @var Account Id of request initiator
     */
    protected $accountId;

    /**
     * Checks if payload is correctly signed
     * @return bool
     */
    public function checkSignature()
    {
        $sign_header = $this->getHeader(self::SIGN_HEADER);
        if (empty($sign_header)) {
            return false;
        }

        $sign_data = explode(':', $sign_header);
        if (count($sign_data) != 3) {
            return false;
        }

        list($nonce, $signature, $publicKey) = $sign_data;

        $memcached = $this->getDi()->getMemcached();
        $accountId = $memcached->get($nonce);
        $memcached->delete($nonce);

        if (empty($accountId) || $accountId != Account::encodeCheck('accountId', $publicKey)) {
            return false;
        }

        $this->accountId = $accountId;

        # Correct signature should consist of base64encoded concatenated uri, req body and nonce
        $s = ($this->getURI() . $this->getRawBody() . $nonce);
        return ed25519_sign_open($s, base64_decode($publicKey), base64_decode($signature));
    }

    /**
     * Returns newly generated nonce
     */
    public function getNonce()
    {
        $memcached = $this->getDi()->getMemcached();
        $config    = $this->getDi()->getConfig();

        $nonce = base64_encode(random_bytes(16));

        $memcached->set($nonce, $this->accountId, $config->nonce->ttl);

        return $nonce;
    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function setAccountId($accountId)
    {
        $this->accountId = $accountId;
    }
}