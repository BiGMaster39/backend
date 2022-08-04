<?php namespace App\Libraries;

/**
 * UID library
 *
 * Using for create uid string for objects and transactions
 */

use Exception;

class Uid
{
    private $bytes;

    /**
     * Create models, config and library's
     * @throws Exception
     */
    function __construct()
    {
        $this->bytes = random_bytes(16);
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Create random UID
     * @return string
     */
    public function create(): string
    {
        assert(strlen($this->bytes) == 16);
        $data[6] = chr(ord($this->bytes[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($this->bytes[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($this->bytes), 4));
    }

    /**
     * Create random branch name
     * @return string
     */
    public function name(): string
    {
        assert(strlen($this->bytes) == 16);
        $data[6] = chr(ord($this->bytes[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($this->bytes[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s_%s_%s_%s_%s%s%s', str_split(bin2hex($this->bytes), 4));
    }
}
