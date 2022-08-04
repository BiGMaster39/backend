<?php
namespace App\Libraries;

use Config\Services;
use App\Models\EmailConfigModel;

/**
 * Email notifications library
 *
 * Using for send email notifications
 */

class Notification
{
    private $config;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->config = new EmailConfigModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Start send email
     * @param string|array $to
     * @param string $subject
     * @param string $message
     * @return void
     */
    public function send(string $to, string $subject, string $message) :void
    {
        $settings = $this->config
            ->where("id", 1)
            ->first();
        if ($settings["status"]) {
            $email = Services::email();
            $config["protocol"]    = 'smtp';
            $config["SMTPHost"]    = $settings["host"];
            $config["SMTPUser"]    = $settings["user"];
            $config["SMTPPass"]    = $settings["password"];
            $config["SMTPPort"]    = $settings["port"];
            $config["SMTPTimeout"] = $settings["timeout"];
            $config["charset"]     = $settings["charset"];
            $config["SMTPCrypto"]  = "ssl";
            $email->initialize($config);
            $email->setFrom($settings["user"], $settings["sender"]);
            $email->setTo($to);
            $email->setSubject($subject);
            $email->setMessage($message);
            $email->send();
        }
    }
}