<?php

namespace App\Models;

use \Basho\Riak;
use \Basho\Riak\Command;
use Phalcon\DI;

class RegUsers extends ModelBase implements ModelInterface
{

    const ID_LENGTH = 8;

    public $id;
    public $ipn_code;             //IPN code
    public $asset;                //asset
    public $surname;              //family name
    public $name;                 //user name
    public $middle_name;          //father's name
    public $email;                //email
    public $phone;                //phone
    public $address;              //address
    public $passport;             //passport series and number

    public function validate() {
        $this->validateIsAllPresent();
    }

    public static function generateID(){

        do {
            $id = '';

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            for ($i = 0; $i < self::ID_LENGTH; $i++) {
                $id .= $characters[rand(0, $charactersLength - 1)];
            }
        } while (self::isExist($id));

        return $id;

    }

    public function __construct($id = null)
    {
        //if $id null - need to generate it for new registered user
        if (empty($id)) {
            $id = self::generateID();
        }

        parent::__construct($id);
        $this->id = $id;
    }

    /**
     * Static method to check if data exists by custom index
     * @param $index - name of index WITHOUT _bin
     * @param $value - value of index
     * @return array -- returns object
     */
    public static function isExistByIndex($index, $value)
    {
        self::setPrimaryAttributes();
        $riak = DI::getDefault()->get('riak');
        return (new Command\Builder\QueryIndex($riak))
            ->buildBucket(self::$BUCKET_NAME)
            ->withIndexName($index . '_bin')
            ->withScalarValue($value)
            ->withMaxResults(1)
            ->build()
            ->execute()
            ->getResults();
    }

    public function create()
    {
        $command = $this->prepareCreate($this->id);

        if (isset($this->ipn_code)) {
            $this->addIndex($command, 'ipn_code_bin', $this->ipn_code);
        }

        if (isset($this->phone)) {
            $this->addIndex($command, 'phone_bin', $this->phone);
        }

        if (isset($this->email)) {
            $this->addIndex($command, 'email_bin', $this->email);
        }

        if (isset($this->passport)) {
            $this->addIndex($command, 'passport_bin', $this->passport);
        }

        return $this->build($command);
    }

    public function update() {
        $command = $this->prepareUpdate();
        //good place to update secondary indexes

        if (isset($this->ipn_code)) {
            $this->addIndex($command, 'ipn_code_bin', $this->ipn_code);
        }

        if (isset($this->phone)) {
            $this->addIndex($command, 'phone_bin', $this->phone);
        }

        if (isset($this->email)) {
            $this->addIndex($command, 'email_bin', $this->email);
        }

        if (isset($this->passport)) {
            $this->addIndex($command, 'passport_bin', $this->passport);
        }

        return $this->build($command);
    }
}