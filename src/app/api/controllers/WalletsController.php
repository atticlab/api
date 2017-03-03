<?php
namespace App\Controllers;

use App\Lib\Response;
use App\Models\Wallets;
use App\Lib\Exception;

use Phalcon\Di;
use Phalcon\Validation;

use \SWP\Validators\UserNameValidator;
use \SWP\Validators\UpdatePasswordValidator;
use \SWP\Validators\CreateWalletValidator;
use \SWP\Validators\WalletIdValidator;
use \SWP\Validators\PhoneNumberValidator;

class WalletsController extends ControllerBase
{
    public function indexAction()
    {
        $keys_path = $this->request->getHttpHost() . $this->request->getURI();

        $explorer = [
            $keys_path . '/v2/wallets/create'            => [
                'method'      => 'post',
                'params'      => [
                    'username'     => [
                        'description' => 'Username. Length - 3-255 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'walletId'     => [
                        'description' => 'Wallet ID. Length - 32 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'accountId'    => [
                        'description' => 'Account ID. Length - 56 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'salt'         => [
                        'description' => 'Salt. Length - 16 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'kdfParams'    => [
                        'description' => 'KdfParams. Must be a valid JSON',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'publicKey'    => [
                        'description' => 'Public Key. Length - 32 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'mainData'     => [
                        'description' => 'Main data',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'keychainData' => [
                        'description' => 'Keychain Data',
                        'type'        => 'string',
                        'required'    => true
                    ]
                ],
                'description' => 'Create wallet'
            ],
            $keys_path . '/v2/wallets/show_login_params' => [
                'method'      => 'post',
                'params'      => [
                    'username' => [
                        'description' => 'Username. Length - 3-255 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ]
                ],
                'description' => 'Show login params by username'
            ],
            $keys_path . '/v2/wallets/show'              => [
                'method'      => 'post',
                'params'      => [
                    'username' => [
                        'description' => 'Username. Length - 3-255 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'walletId' => [
                        'description' => 'Wallet ID. Length - 32 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                ],
                'description' => 'Show wallet data by username and wallet ID'
            ],
            $keys_path . '/v2/wallets/update'            => [
                'method'      => 'post',
                'params'      => [
                    'phone' => [
                        'description' => 'Phone. Must be a valid mobile phone number. Length - 10 symbols',
                        'type'        => 'string',
                        'required'    => false
                    ],
                    'email' => [
                        'description' => 'User email. Must be a valid email address',
                        'type'        => 'string',
                        'required'    => false
                    ],
                    'HDW'   => [
                        'description' => 'HDW',
                        'type'        => 'string',
                        'required'    => false
                    ],
                ],
                'description' => 'Update wallet by phone, email or HDW'
            ],
            $keys_path . '/v2/wallets/updatePassword'    => [
                'method'      => 'post',
                'params'      => [
                    'walletId'     => [
                        'description' => 'Wallet ID. Length - 32 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'salt'         => [
                        'description' => 'Salt. Length - 16 symbols',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'kdfParams'    => [
                        'description' => 'KdfParams. Must be a valid JSON',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'mainData'     => [
                        'description' => 'Main data',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'keychainData' => [
                        'description' => 'Keychain Data',
                        'type'        => 'string',
                        'required'    => true
                    ],
                    'lockVersion'  => [
                        'description' => 'Lock version',
                        'type'        => 'int',
                        'required'    => true
                    ],
                ],
                'description' => 'Update wallet password'
            ]
        ];

        return $this->response->json($explorer, false);
    }

    public function getkdfAction()
    {

        $data = [
            'algorithm' => 'scrypt',
            'bits'      => 256,
            'n'         => pow(2, 12),
            'r'         => 8,
            'p'         => 1
        ];

        return $this->response->json($data, false);
    }

    /**
     * This is getLoginParams action,
     * that takes an username in post
     * @return \Phalcon\Http\Response
     */
    public function getparamsAction()
    {
        $validation = new UserNameValidator();
        $this->doValidation($validation, $this->payload);

        if (!Wallets::isExist($this->payload->username)) {
            return $this->response->error(Response::ERR_NOT_FOUND);
        }

        $wallet = Wallets::getDataByID($this->payload->username);

        $preparedData['salt'] = $wallet->salt;
        $preparedData['kdfParams'] = $wallet->kdfParams;
        $this->logger->debug('Login params received',
            ['Path' => '/v2/wallets/show_login_params', 'Data:' => $preparedData]);

        return $this->response->json($preparedData, false);
    }

    /**
     * This is Create Wallet action
     */
    public function createAction()
    {
        $validation = new CreateWalletValidator();
        $this->doValidation($validation, $this->payload);
        $validation = new UserNameValidator();
        $this->doValidation($validation, $this->payload);

        if (Wallets::isExist($this->payload->username)) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'username');
        }

        $wallet = new Wallets($this->payload->username);
        $wallet->walletId = $this->payload->walletId;
        $wallet->accountId_s = @$this->payload->accountId;
        $wallet->salt = $this->payload->salt;
        $wallet->kdfParams = stripcslashes($this->payload->kdfParams);
        $wallet->publicKey = $this->payload->publicKey;
        $wallet->mainData = $this->payload->mainData;
        $wallet->keychainData = $this->payload->keychainData;
        $wallet->createdAt = date('D M d Y H:i:s O');
        $wallet->updatedAt = $wallet->createdAt;

        try {
            if (!$wallet->create()) {
                $this->logger->emergency('Riak error while creating invoice');
                throw new Exception(Exception::SERVICE_ERROR);
            }

            return $this->response->json([], false);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    /**
     * This is Create Wallet action
     */
    public function createWithPhoneAction()
    {
        $validation = new CreateWalletValidator();
        $this->doValidation($validation, $this->payload);
        $validation->add('username', new PhoneNumberValidator);

        if (Wallets::isExist($this->payload->username)) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'username');
        }

        $wallet = new Wallets($this->payload->username);
        $wallet->walletId = $this->payload->walletId;
        $wallet->accountId_s = @$this->payload->accountId;
        $wallet->salt = $this->payload->salt;
        $wallet->kdfParams = stripcslashes($this->payload->kdfParams);
        $wallet->publicKey = $this->payload->publicKey;
        $wallet->mainData = $this->payload->mainData;
        $wallet->keychainData = $this->payload->keychainData;
        $wallet->createdAt = date('D M d Y H:i:s O');
        $wallet->updatedAt = $wallet->createdAt;
        $wallet->phone_s = $this->payload->username;

        try {
            if (!$wallet->create()) {
                $this->logger->emergency('Riak error while creating invoice');
                throw new Exception(Exception::SERVICE_ERROR);
            }

            return $this->response->json([], false);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function notExistAction()
    {
        if (empty($this->payload->username)) {
            return $this->response->error(Response::ERR_EMPTY_PARAM, 'username');
        }
        if (Wallets::isExist($this->payload->username)) {
            return $this->response->error(Response::ERR_ALREADY_EXISTS, 'username');
        }

        return $this->response->json([], false);
    }

    /**
     * This is showAction that shows Wallet data by
     * username and walletId
     * @return \Phalcon\Http\Response
     */
    public function getAction()
    {
        $validation = new UserNameValidator();
        $this->doValidation($validation, $this->payload);
        $validation = new WalletIdValidator();
        $this->doValidation($validation, $this->payload);
        if (!Wallets::isExist($this->payload->username)) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'username');
        }

        $wallet = Wallets::getDataByID($this->payload->username);

        //walletID check
        if ($this->payload->walletId != $wallet->walletId) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'wallet');
        }

        $preparedData['mainData'] = $wallet->mainData;
        $preparedData['keychainData'] = $wallet->keychainData;
        $preparedData['email'] = $wallet->email;
        $preparedData['phone'] = $wallet->phone;
        $preparedData['HDW'] = $wallet->HDW;

        return $this->response->json($preparedData, false);
    }

    public function updateAction()
    {
        $update = false;

        try {
            $wallet_data = Wallets::findFirstByField('accountId_s', $this->request->getAccountId());
            if (!$wallet_data) {
                return $this->response->error(Response::ERR_NOT_FOUND, 'wallet');
            }
            $wallet = Wallets::findFirst($wallet_data->username);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        if (isset($this->payload->phone) && $this->payload->phone != $wallet->phone_s) {
            if (!empty($this->payload->phone)) {
                $validation = new Validation();
                $validation->add('phone', new PhoneNumberValidator);
                $messages = $validation->validate($this->payload);
                if (count($messages)) {
                    return $this->response->error(Response::ERR_BAD_PARAM, 'phone');
                }
                // Check if this phone exists
                if (Wallets::isExistByField('phone_s', $this->payload->phone)) {
                    return $this->response->error(Response::ERR_ALREADY_EXISTS, 'phone');
                }
                $update = true;
                $wallet->phone_s = $this->payload->phone;
            } else {
                $update = true;
                $wallet->phone_s = $this->payload->phone;
            }
        }

        if (isset($this->payload->email) && (strtolower($this->payload->email) != strtolower($wallet->email_s))) {
            if (!empty($this->payload->email)) {
                if (!filter_var($this->payload->email, FILTER_VALIDATE_EMAIL)) {
                    return $this->response->error(Response::ERR_BAD_PARAM, 'email');
                }
                // Check if this email exists
                if (Wallets::isExistByField('email_s', $this->payload->email)) {
                    return $this->response->error(Response::ERR_ALREADY_EXISTS, 'email');
                }
                $update = true;
                $wallet->email_s = $this->payload->email;
            } else {
                $update = true;
                $wallet->email_s = $this->payload->email;
            }
        }

        if (!empty($this->payload->HDW) && $this->payload->HDW != $wallet->HDW) {
            $update = true;
            $wallet->HDW = $this->payload->HDW;
        }

        if (!$update) {
            return $this->response->json([], false);
        }

        try {
            if ($wallet->update()) {
                $preparedData = [
                    'email' => $wallet->email_s,
                    'phone' => $wallet->phone_s,
                    'HDW'   => $wallet->HDW
                ];

                return $this->response->json($preparedData, false);
            }
            $this->logger->emergency('Riak error while updating wallet');
            throw new Exception(Exception::SERVICE_ERROR);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

    }

    public function updatePasswordAction()
    {
        try {
            $wallet_data = Wallets::findFirstByField('accountId_s', $this->request->getAccountId());
            if (!$wallet_data) {
                return $this->response->error(Response::ERR_NOT_FOUND, 'wallet');
            }
            $wallet = Wallets::findFirst($wallet_data->username);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }

        $validation = new UpdatePasswordValidator();
        $this->doValidation($validation, $this->payload);

        $wallet->walletId = $this->payload->walletId;
        $wallet->salt = $this->payload->salt;
        $wallet->kdfParams = $this->payload->kdfParams;
        $wallet->mainData = $this->payload->mainData;
        $wallet->keychainData = $this->payload->keychainData;

        try {
            if ($wallet->update()) {
                return $this->response->json([], false);
            }
            $this->logger->emergency('Riak error while updating wallet');
            throw new Exception(Exception::SERVICE_ERROR);
        } catch (Exception $e) {
            return $this->handleException($e->getCode(), $e->getMessage());
        }
    }

    public function getWalletDataAction()
    {
        $email = $this->payload->email ?? null;
        $phone = $this->payload->phone ?? null;
        $account_id = $this->payload->accountId ?? null;
        $username = $this->payload->username ?? null;

        if (
            empty($email) &&
            empty($phone) &&
            empty($account_id) &&
            empty($username)
        ) {
            return $this->response->error(Response::ERR_BAD_PARAM, 'criteria');
        }

        if (!empty($email)) {
            $wallet = Wallets::findFirstByField('email_s', $email);
        } elseif (!empty($phone)) {
            $wallet = Wallets::findFirstByField('phone_s', $phone);
        } elseif (!empty($account_id)) {
            $wallet = Wallets::findFirstByField('accountId_s', $account_id);
        } else {
            $wallet = Wallets::getDataByID($username);
        }

        if (!$wallet) {
            return $this->response->error(Response::ERR_NOT_FOUND, 'wallet');
        }

        $preparedData['accountId'] = $wallet->accountId;
        $preparedData['phone'] = $wallet->phone;
        $preparedData['email'] = $wallet->email;
        $preparedData['uniqueId'] = $wallet->uniqueId;

        return $this->response->json($preparedData, false);
    }

    /**
     * This is a service function for simplifying validation
     * @param $validation -- a Validation object
     * @param $params     -- an assoc array of params
     * @return mixed -- an assoc array of return params
     */
    private function doValidation($validation, $params)
    {
        if (!is_array($params)) {
            $params = (array)$params;
        }
        $messages = $validation->validate($params);
        if (count($messages)) {
            foreach ($validation->getMessages() as $message) {
                return $this->response->error(Response::ERR_BAD_PARAM, $message->getMessage());
            }
        }

        return true;
    }

    public function notFoundAction()
    {
        return $this->response->error(Response::ERR_NOT_FOUND);
    }
}