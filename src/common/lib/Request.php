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
        $session = $this->getDi()->getSession();
        $current_nonce = $session->nonce;

        $session->nonce = base64_encode(random_bytes(32));
        if (empty($current_nonce)) {
            return false;
        }

//        error_log(print_r($this->getHeaders(), true));

        // if bad signature return false
        return false;
    }

    /**
     * Returns newly generated nonce
     */
    public function getNonce()
    {
        $session = $this->getDi()->getSession();

        return $session->nonce;
    }
}