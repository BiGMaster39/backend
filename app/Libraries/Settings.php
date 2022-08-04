<?php
namespace App\Libraries;

use App\Models\SettingsModel;

class Settings
{
    private $settings;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->settings = new SettingsModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get settings value by key
     * @param string $key
     * @return null|string
     */
    public function get_config(string $key): ?string
    {
        $config = $this->settings
            ->where("set_key", esc($key))
            ->first();
        return !$config ? null : $config["value"];
    }
}