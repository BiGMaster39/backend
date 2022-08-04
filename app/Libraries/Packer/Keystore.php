<?php namespace App\Libraries\Packer;

use App\Libraries\Settings;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use Config\Services;

class Keystore
{
    private $settings;
    private $keyIssuer = "https://license.flangapp.com/backend/";

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->settings = new Settings();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Generate android *jks signature
     * @return array
     */
    public function generate_android() : array
    {
        $client = Services::curlrequest();
        helper('text');

        $alias = random_string("alpha", 10);
        $password = random_string('alnum', 16);
        $name = random_string("alpha", 8);

        try {
            $response = $client->request('POST', $this->keyIssuer.'api/signing/android', [
                "headers" => [
                    "License"     => $this->settings->get_config("license"),
                    "User-Agent"  => site_url()
                ],
                "json" => [
                    "alias"    => $alias,
                    "password" => $password,
                    "name"     => $name
                ]
            ]);
            $fp = fopen(WRITEPATH.'storage/android/'.$name.'.jks',"wb");
            fwrite($fp, $response->getBody());
            fclose($fp);
            return [
                "event"    => true,
                "alias"    => $alias,
                "password" => $password,
                "name"     => $name
            ];
        } catch (HTTPException $e) {
            return [
                "event"    => false,
                "error"    => $e->getMessage()
            ];
        }
    }
}