<?php namespace App\Libraries\Packer;

class DartTypeGenerator
{
    /**
     * Create models, config and library's
     */
    function __construct()
    {

    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get typed layout template value
     * @param int $template
     * @return string
     */
    public function get_layout(int $template): string
    {
        switch($template)
        {
            case 0 :
                return "Template.drawer";
            case 1 :
                return "Template.tabs";
            case 2 :
                return "Template.bar";

            default :
                return "Template.blank";
        }
    }

    /**
     * Get typed loader mode value
     * @param int $loader
     * @return string
     */
    public function get_loader_mode(int $loader): string
    {
        switch($loader)
        {
            case 0 :
                return "LoadIndicator.none";
            case 1 :
                return "LoadIndicator.line";

            default :
                return "LoadIndicator.spinner";
        }
    }

    /**
     * Get typed splashscreen mode value
     * @param int $mode
     * @return string
     */
    public function get_splash_mode(int $mode): string
    {
        if (!$mode) {
            return "SplashBackground.Color";
        } else {
            return "SplashBackground.Image";
        }
    }

    /**
     * Get typed splashscreen text theme value
     * @param int $theme
     * @return string
     */
    public function get_splash_theme(int $theme): string
    {
        if (!$theme) {
            return "SplashTheme.Light";
        } else {
            return "SplashTheme.Dark";
        }
    }

    /**
     * Get typed drawer mode value
     * @param int $mode
     * @return string
     */
    public function get_drawer_mode(int $mode): string
    {
        switch($mode)
        {
            case 0 :
                return "BackgroundMode.none";
            case 1 :
                return "BackgroundMode.color";

            default :
                return "BackgroundMode.image";
        }
    }

    /**
     * Get CSS blocks for app hide
     * @param array $divs
     * @return string
     */
    public function get_css_block(array $divs): string
    {
        $blocks = "";
        foreach ($divs as $div) {
            $blocks .= '".'.$div["name"].'",';
        }
        return $blocks;
    }

    /**
     * Get navigation values
     * @param array $navs
     * @return string
     */
    public function get_navigation_items(array $navs): string
    {
        $items = "";
        foreach ($navs as $nav) {
            $type = $this->get_navigation_mode($nav["type"], $nav["link"]);
            $items .= "\tNavigationItem(";
            $items .= 'name: "'.$nav["name"].'",';
            $items .= 'icon: "'.$nav["icon"].'.svg",';
            $items .= 'type: '.$type.',';
            $items .= 'value: "'.$nav["link"].'",';
            $items .= "),\n";
        }
        return $items;
    }

    /**
     * Get typed navigation mode value
     * @param int $mode
     * @return string
     */
    public function get_navigation_mode(int $mode): string
    {
        switch($mode)
        {
            case 0 :
                return "ActionType.internal";
            case 1 :
                return "ActionType.external";
            case 2 :
                return "ActionType.share";
            case 3 :
                return "ActionType.email";
            case 4 :
                return "ActionType.phone";
            default :
                return "ActionType.openModal";
        }
    }

    /**
     * Get orientation value for android app
     * @param int $mode
     * @return string
     */
    public function get_orientation_mode_android(int $mode): string
    {
        switch($mode)
        {
            case 0 :
                return "unspecified";
            case 1 :
                return "portrait";

            default :
                return "landscape";
        }
    }

    /**
     * Get orientation value for ios app
     * @param int $mode
     * @return string
     */
    public function get_orientation_mode_ios(int $mode): string
    {
        $strings = "";
        if (!$mode) {
            $strings .= "<string>UIInterfaceOrientationPortrait</string>\n";
            $strings .= "<string>UIInterfaceOrientationLandscapeLeft</string>\n";
            $strings .= "<string>UIInterfaceOrientationLandscapeRight</string>\n";
        } else if ($mode == 1) {
            $strings .= "<string>UIInterfaceOrientationPortrait</string>\n";
        } else {
            $strings .= "<string>UIInterfaceOrientationLandscapeLeft</string>\n";
            $strings .= "<string>UIInterfaceOrientationLandscapeRight</string>\n";
        }
        return $strings;
    }

    /**
     * Get permissions camera for ios
     * @param int $value
     * @return string
     */
    public function get_permissions_camera_ios(int $value): ?string
    {
        $strings = "";
        if ($value) {
            $strings .= "<key>NSCameraUsageDescription</key>\n";
            $strings .= "<string>Access to the camera will allow you to create photos</string>\n";
        }
        return $value ? $strings : null;
    }

    /**
     * Get permissions geo for android
     * @param int $value
     * @return string
     */
    public function get_permissions_gps_android(int $value): ?string
    {
        $strings = "";
        if ($value) {
            $strings .= '<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />';;
            $strings .= '<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />';;
            $strings .= '<uses-permission android:name="android.permission.ACCESS_BACKGROUND_LOCATION" />';
            $strings .= '<uses-permission android:name="android.permission.ACCESS_GPS" />';
            $strings .= '<uses-feature android:name="android.hardware.location.gps" />';
        }
        return $strings;
    }

    /**
     * Get permissions camera for android
     * @param int $value
     * @return string
     */
    public function get_permissions_camera_android(int $value): ?string
    {
        $strings = "";
        if ($value) {
            $strings .= '<uses-permission android:name="android.permission.CAMERA" />';
        }
        return $strings;
    }

    /**
     * Get permissions microphone for android
     * @param int $value
     * @return string
     */
    public function get_permissions_microphone_android(int $value): ?string
    {
        $strings = "";
        if ($value) {
            $strings .= '<uses-permission android:name="android.permission.RECORD_AUDIO" />';
        }
        return $strings;
    }

    /**
     * Get permissions camera for gps
     * @param int $value
     * @return string
     */
    public function get_permissions_gps_ios(int $value): ?string
    {
        $strings = "";
        if ($value) {
            $strings .= "<key>NSLocationWhenInUseUsageDescription</key>\n";
            $strings .= "<string>Access to the location will allow you to track and show your location</string>\n";
            $strings .= "<key>NSLocationAlwaysUsageDescription</key>\n";
            $strings .= "<string>Access to the location will allow you to track and show your geo location</string>\n";
        }
        return $value ? $strings : null;
    }

    /**
     * Get permissions camera for microphone
     * @param int $value
     * @return string
     */
    public function get_permissions_microphone_ios(int $value): ?string
    {
        $strings = "";
        if ($value) {
            $strings .= "<key>NSMicrophoneUsageDescription</key>\n";
            $strings .= "<string>Access to the location will allow you to record sound</string>\n";
        }
        return $value ? $strings : null;
    }

    /**
     * Get formatted ios private key string
     * @param string $key
     * @return string
     */
    public function get_formatted_ios_private_key(string $key): string
    {
        $string = "";
        foreach(preg_split("/((\r?\n)|(\r\n?))/", $key) as $line){
            $string .= "         $line\n";
        }
        return $string;
    }
}