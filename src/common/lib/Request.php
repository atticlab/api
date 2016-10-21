<?php

namespace App\lib;

class Request extends \Phalcon\Http\Request
{
    /**
     * Checks if nonce signature is valid
     * @return bool
     */
    public function checkSignature()
    {

        error_log(print_r($this->getHeader('Signed-Nonce'), true));

        $session = $this->getDi()->getSession();
        if (empty($session->nonce)) {
            return false;
        }

        error_log(print_r($this->getHeaders(), true));

        // if bad signature return false
        return true;
    }

    /**
     * Returns newly generated nonce
     */
    public function getNonce()
    {
        $session = $this->getDi()->getSession();
        $session->nonce = base64_encode(random_bytes(32));

        return $session->nonce;
    }
}