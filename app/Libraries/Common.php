<?php
namespace App\Libraries;

use App\Models\NavigationModel;
use App\Models\BarNavigationModel;
use App\Models\ModalNavigationModel;

class Common
{
    private $mainNavigation;
    private $barNavigation;
    private $modalNavigation;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->mainNavigation = new NavigationModel();
        $this->barNavigation = new BarNavigationModel();
        $this->modalNavigation = new ModalNavigationModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Validation color HEX type
     * @param string $color
     * @return boolean
     */
    public function hex_validation(string $color): bool
    {
        $short_color = substr($color, 1);
        return ctype_xdigit($short_color) && strlen($short_color) == 6;
    }

    /**
     * Validation link website
     * @param string $uri
     * @return boolean
     */
    public function uri_validation(string $uri): bool
    {
        return preg_match( '/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*\\.[_a-z]{2,5}'.'((:[0-9]{1,5})?\\/.*)?$/i', $uri);
    }

    /**
     * Validation email
     * @param string $email
     * @return boolean
     */
    public function email_validation(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Get site host value
     * @param $link
     * @return string
     */
    public function get_site_host($link): string
    {
        $parse = parse_url($link);
        return $parse['host'];
    }

    /**
     * Get version form validation
     * @param $version
     * @return bool
     */
    public function version_format_validation($version): bool
    {
        $array = explode('.', $version);
        if (count($array) != 3) {
            return false;
        } else {
            if (!is_numeric($array[0])
                || !is_numeric($array[1])
                || !is_numeric($array[2])) {
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * Check existence icon in repo
     * @param $app_id
     * @param $icon_name
     * @return bool
     */
    public function icon_ex_validation($app_id, $icon_name): bool
    {
        $ex_main = $this->mainNavigation
            ->where(["app_id" => $app_id, "icon" => $icon_name])
            ->countAllResults();
        $ex_bar = $this->barNavigation
            ->where(["app_id" => $app_id, "icon" => $icon_name])
            ->countAllResults();
        $ex_modal = $this->modalNavigation
            ->where(["app_id" => $app_id, "icon" => $icon_name])
            ->countAllResults();
        if ($ex_main > 0 || $ex_bar > 0 || $ex_modal > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check existence icon in repo for remove
     * @param $app_id
     * @param $icon_name
     * @return bool
     */
    public function is_remove_icon($app_id, $icon_name): bool
    {
        $ex_main = $this->mainNavigation
            ->where(["app_id" => $app_id, "icon" => $icon_name])
            ->countAllResults();
        $ex_bar = $this->barNavigation
            ->where(["app_id" => $app_id, "icon" => $icon_name])
            ->countAllResults();
        $ex_modal = $this->modalNavigation
            ->where(["app_id" => $app_id, "icon" => $icon_name])
            ->countAllResults();
        if (($ex_modal + $ex_main + $ex_bar - 1) > 0) {
            return false;
        } else {
            return true;
        }
    }
}