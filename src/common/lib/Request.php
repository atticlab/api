<?php

namespace App\lib;
use Smartmoney\Stellar\Account;

class Request extends \Phalcon\Http\Request
{
    /**
     * @var Account Id of request initiator
     */
    protected $accountId;
    /**
     * Checks if nonce signature is valid
     * @return bool
     */
    public function checkSignature()
    {
        $signed_nonce_header = $this->getHeader('Signed-Nonce');

        var_dump($signed_nonce_header);

        if (empty($signed_nonce_header)) {
            return false;
        }

        $sign_data = explode(':', $signed_nonce_header);

        var_dump($sign_data);

        if (count($sign_data) != 3) {
            error_log('bad_sign_data_count');
            return false;
        }

        $memcached = $this->getDi()->getMemcached();

        $old_nonce    = $sign_data[0];
        $signed_nonce = $sign_data[1];
        $publicKey    = $sign_data[2];

        $accountId = $memcached->get($old_nonce);

        var_dump($accountId);

        $memcached->delete($old_nonce);

        if (empty($accountId) || $accountId != Account::encodeCheck('accountId', $publicKey)) {
            error_log($accountId);
            error_log($publicKey);
            error_log('bad_acc_id');
            return false;
        }

        $this->accountId = $accountId;
        if(!ed25519_sign_open($old_nonce, base64_decode($publicKey), base64_decode($signed_nonce))){
            error_log('bad_ed25519');
        }
        return ed25519_sign_open($old_nonce, base64_decode($publicKey), base64_decode($signed_nonce));

    }

    /**
     * Returns newly generated nonce
     */
    public function getNonce()
    {
        $memcached = $this->getDi()->getMemcached();
        $config    = $this->getDi()->getConfig();

        $nonce = base64_encode(random_bytes(16));

        var_dump($this->accountId);
        var_dump($memcached->set($nonce, $this->accountId, $config->nonce->ttl));

        //$memcached->set($nonce, $this->accountId, $config->nonce->ttl);

        return $nonce;
    }

    public function getAccountId()
    {
        return $this->accountId;
    }

    public function setAccountId($accountId)
    {
        var_dump('here:' . $accountId);
        $this->accountId = $accountId;
    }
}