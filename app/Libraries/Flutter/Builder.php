<?php namespace App\Libraries\Flutter;

use App\Libraries\GitHub;
use App\Libraries\Packer\DartTypeGenerator;
use App\Models\BarNavigationModel;
use App\Models\ModalNavigationModel;
use App\Models\DrawersModel;
use App\Models\LocalsModel;
use App\Models\NavigationModel;
use App\Models\PushModel;
use App\Models\SignsAndroidModel;
use App\Models\SignsIosModel;
use App\Models\SplashScreensModel;
use App\Models\BuildsModel;
use App\Models\StylesModel;
use CodeIgniter\Config\Services;

class Builder
{
    private $splash;
    private $push;
    private $drawers;
    private $locals;
    private $dartTypes;
    private $styles;
    private $barNavigation;
    private $modalNavigation;
    private $navigation;
    private $github;
    private $android_signs;
    private $ios_signs;
    private $builds;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->splash = new SplashScreensModel();
        $this->push = new PushModel();
        $this->drawers = new DrawersModel();
        $this->locals = new LocalsModel();
        $this->dartTypes = new DartTypeGenerator();
        $this->styles = new StylesModel();
        $this->barNavigation = new BarNavigationModel();
        $this->modalNavigation = new ModalNavigationModel();
        $this->navigation = new NavigationModel();
        $this->github = new GitHub();
        $this->android_signs = new SignsAndroidModel();
        $this->ios_signs = new SignsIosModel();
        $this->builds = new BuildsModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Generate android app project
     * @param array $app
     * @param string $version
     * @param string $format
     * @param int $sign_id
     * @return array
     */
    public function generate_android_project(array $app, string $version, string $format, int $sign_id): array
    {
        $config_result = $this->generate_config($app, $version);
        if (!$config_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_35")
                ]
            ];
        }
        $manifest_result = $this->generate_android_manifest($app);
        if (!$manifest_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_36")
                ]
            ];
        }
        $pub_result = $this->generate_pubspec_yaml($version, $app["uid"]);
        if (!$pub_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_37")
                ]
            ];
        }
        $gradle_result = $this->generate_gradle($app["app_id"], $app["uid"], $sign_id, $app["id"], $version);
        if (!$gradle_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_38")
                ]
            ];
        }
        $yaml_result = $this->generate_cm_android($app["app_id"], $app["uid"], $format);
        if (!$yaml_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_41")
                ]
            ];
        }
        return [
            "event" => true
        ];
    }

    /**
     * Generate ios app project
     * @param array $app
     * @param string $version
     * @param int $sign_id
     * @param int $publish
     * @return array
     */
    public function generate_ios_project(array $app, string $version, int $sign_id, int $publish): array
    {
        $config_result = $this->generate_config($app, $version);
        if (!$config_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_35")
                ]
            ];
        }
        $plist_result = $this->generate_plist($app);
        if (!$plist_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_43")
                ]
            ];
        }
        $yaml_result = $this->generate_cm_ios($app["app_id"], $app["uid"], $sign_id, $publish);
        if (!$yaml_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_41")
                ]
            ];
        }
        $pbxproj_result = $this->generate_pbxproj($app["app_id"], $app["uid"]);
        if (!$pbxproj_result["event"]) {
            return [
                "event" => false,
                "message" => [
                    "error" => lang("Message.message_44")
                ]
            ];
        }
        return [
            "event" => true
        ];
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Generate codemagic.yaml for android build
     * @param string $app_id
     * @param string $app_uid
     * @param string $format
     * @return array
     */
    private function generate_cm_android(string $app_id, string $app_uid, string $format): array
    {
        // codemagic.yaml example file
        $yamlFile = file_get_contents($format === "apk"
            ? WRITEPATH.'project/workflow/android_apk.yaml'
            : WRITEPATH.'project/workflow/android_aab.yaml'
        );
        $yamlFileVariables = [
            '{WORKFLOW_NAME}',
            '{APP_ID}',
            '{NOTICE_URL}'
        ];
        $yamlCodeVariable = [
            "SiteNative Android ".$format,
            $app_id,
            base_url("api/observe/notice/".$app_uid)
        ];
        // replace
        $content = str_replace($yamlFileVariables, $yamlCodeVariable, $yamlFile);
        // upload yaml
        return $this->github->create_commit($app_uid, "codemagic.yaml", $content);
    }

    /**
     * Generate gradle file
     * @param string $app_id
     * @param string $uid
     * @param int $sign_id
     * @param int $id_app
     * @param string $version
     * @return array
     */
    private function generate_gradle(string $app_id, string $uid, int $sign_id, int $id_app, string $version): array
    {
        $encrypter = Services::encrypter();
        $sign = $this->android_signs
            ->where("id", $sign_id)
            ->first();
        $count = $this->builds
            ->where(["app_id" => $id_app])
            ->countAllResults();
        // gradle example file
        $gradleFile = file_get_contents(WRITEPATH.'project/android/build.gradle');
        $configFileVariables = [
            '{APP_ID}',
            '{KEYSTORE_PASSWORD}',
            '{KEY_PASSWORD}',
            '{KEY_ALIAS}',
            '{VERSION_CODE}',
            '{VERSION_NAME}'
        ];
        $configCodeVariable = [
            $app_id,
            $encrypter->decrypt($sign["keystore_password"]),
            $encrypter->decrypt($sign["key_password"]),
            $sign["alias"],
            $count + 1,
            $version
        ];
        // replace
        $content = str_replace($configFileVariables, $configCodeVariable, $gradleFile);
        // github commit
        $resultGrade = $this->github->create_commit($uid, "android/app/build.gradle", $content);
        if ($resultGrade["event"]) {
            // upload keystore
            return $this->github->create_commit(
                $uid,
                "android/app/sign/key.jks",
                file_get_contents(WRITEPATH.'storage/android/'.$sign["file"])
            );
        } else {
            return $resultGrade;
        }
    }

    /**
     * Generate pubspec.yaml file
     * @param string $version
     * @param string $uid
     * @return array
     */
    private function generate_pubspec_yaml(string $version, string $uid): array
    {
        // pubspec example file
        $pubFile = file_get_contents(WRITEPATH.'project/android/pubspec.yaml');
        $configFileVariables = [
            '{APP_VERSION}'
        ];
        $configCodeVariable = [
            $version,
        ];
        // replace
        $content = str_replace($configFileVariables, $configCodeVariable, $pubFile);
        // github commit
        return $this->github->create_commit($uid, "pubspec.yaml", $content);
    }

    /**
     * Generate android manifest
     * @param array $app
     * @return array
     */
    private function generate_android_manifest(array $app): array
    {
        // manifest example file
        $manifestFile = file_get_contents(WRITEPATH.'project/android/AndroidManifest.xml');
        $configFileVariables = [
            '{APP_NAME}',
            '{APP_ORIENTATION}',
            '{GEO_PERMISSIONS}',
            '{CAMERA_PERMISSIONS}',
            '{MICROPHONE_PERMISSIONS}'
        ];
        $configCodeVariable = [
            $app["name"],
            $this->dartTypes->get_orientation_mode_android($app["orientation"]),
            $this->dartTypes->get_permissions_gps_android($app["gps"]),
            $this->dartTypes->get_permissions_camera_android($app["camera"]),
            $this->dartTypes->get_permissions_microphone_android($app["microphone"])
        ];
        // replace
        $content = str_replace($configFileVariables, $configCodeVariable, $manifestFile);
        // github commit
        return $this->github->create_commit($app["uid"], "android/app/src/main/AndroidManifest.xml", $content);
    }

    /**
     * Generate codemagic.yaml for ios
     * @param string $app_id
     * @param string $app_uid
     * @param int $sign_id
     * @param int $publish
     * @return array
     */
    private function generate_cm_ios(string $app_id, string $app_uid, int $sign_id, int $publish): array
    {
        $yamlFile = file_get_contents(!$publish
            ? WRITEPATH.'project/workflow/ios.yaml'
            : WRITEPATH.'project/workflow/ios_publish.yaml'
        );
        $sign = $this->ios_signs
            ->where("id", $sign_id)
            ->first();
        $yamlFileVariables = [
            '{WORKFLOW_NAME}',
            '{APP_ID}',
            '{APP_STORE_CONNECT_ISSUER_ID}',
            '{APP_STORE_CONNECT_KEY_IDENTIFIER}',
            '{APP_STORE_CONNECT_PRIVATE_KEY}',
            '{NOTICE_URL}',
            '{CERTIFICATE_PRIVATE_KEY}'
        ];
        $yamlCodeVariable = [
            "SiteNative iOS without OneSignal",
            $app_id,
            $sign["issuer_id"],
            $sign["key_id"],
            $this->dartTypes->get_formatted_ios_private_key(
                file_get_contents(WRITEPATH.'storage/ios/'.$sign["file"])
            ),
            base_url("api/observe/notice/".$app_uid),
            $this->dartTypes->get_formatted_ios_private_key(
                file_get_contents(WRITEPATH.'storage/pub/'.$sign["uid"])
            ),
        ];
        // replace
        $content = str_replace($yamlFileVariables, $yamlCodeVariable, $yamlFile);
        return $this->github->create_commit($app_uid, "codemagic.yaml", $content);
    }

    /**
     * Generate project pbxproj
     * @param string $app_id
     * @param string $app_uid
     * @return array
     */
    private function generate_pbxproj(string $app_id, string $app_uid): array
    {
        $pbxprojFile = file_get_contents(WRITEPATH.'project/ios/project.pbxproj');
        $pbxprojFileVariables = [
            '{APP_ID}'
        ];
        $pbxprojCodeVariable = [
            $app_id
        ];
        // replace
        $content = str_replace($pbxprojFileVariables, $pbxprojCodeVariable, $pbxprojFile);
        // github commit
        return $this->github->create_commit($app_uid, "ios/Runner.xcodeproj/project.pbxproj", $content);
    }

    /**
     * Generate plist file
     * @param array $app
     * @return array
     */
    private function generate_plist(array $app): array
    {
        $plistFile = file_get_contents(WRITEPATH.'project/ios/info.plist');
        $plistFileVariables = [
            '{APP_NAME}',
            '{APP_ORIENTATION}',
            '{APP_LANGUAGE}',
            '{GPS}',
            '{CAMERA}',
            '{MICROPHONE}',
        ];
        $plistCodeVariable = [
            $app["name"],
            $this->dartTypes->get_orientation_mode_ios($app["orientation"]),
            strtolower($app["language"]),
            $this->dartTypes->get_permissions_gps_ios($app["gps"]),
            $this->dartTypes->get_permissions_camera_ios($app["camera"]),
            $this->dartTypes->get_permissions_microphone_ios($app["microphone"])
        ];
        // replace
        $content = str_replace($plistFileVariables, $plistCodeVariable, $plistFile);
        // github commit
        return $this->github->create_commit($app["uid"], "ios/Runner/Info.plist", $content);
    }

    /**
     * Generate configuration file
     * @param array $app
     * @param string $version
     * @return array
     */
    private function generate_config(array $app, string $version): array
    {
        $encrypter = Services::encrypter();
        // config example file
        $configFile = file_get_contents(WRITEPATH.'project/Config.dart');
        // splashscreen settings
        $splashscreen = $this->splash
            ->where("app_id", $app["id"])
            ->first();
        // OneSignal settings
        $onesignal = $this->push
            ->where("app_id", $app["id"])
            ->first();
        // Drawer settings
        $drawer = $this->drawers
            ->where("app_id", $app["id"])
            ->first();
        // Local settings
        $local = $this->locals
            ->where("app_id", $app["id"])
            ->first();
        // DIV blocks for hide
        $styles = $this->styles
            ->where("app_id", $app["id"])
            ->findAll();
        $bar_navigation = $this->barNavigation
            ->where("app_id", $app["id"])
            ->findAll();
        $main_navigation = $this->navigation
            ->where("app_id", $app["id"])
            ->findAll();
        $modal_navigation = $this->modalNavigation
            ->where("app_id", $app["id"])
            ->findAll();
        $configFileVariables = [
            '{GENERATE_DATE}',
            '{APP_UID}',
            '{API_SERVER}',
            '{APP_NAME}',
            '{APP_LINK}',
            '{APP_DISPLAY_TITLE}',
            '{APP_COLOR}',
            '{APP_COLOR_TITLE}',
            '{APP_PULL_TO_REFRESH}',
            '{APP_USER_AGENT}',
            '{APP_VERSION}',
            '{APP_EMAIL}',
            '{APP_TEMPLATE}',
            '{LOADER_INDICATOR}',
            '{LOADER_COLOR}',
            '{ACCESS_CAMERA}',
            '{ACCESS_MICROPHONE}',
            '{ACCESS_GEOLOCATION}',
            '{CSS_HIDE_BLOCKS}',
            '{SPLASH_IS_IMAGE}',
            '{SPLASH_BACKGROUND_COLOR}',
            '{SPLASH_TAGLINE}',
            '{SPLASH_TEXT_COLOR}',
            '{SPLASH_DELAY}',
            '{SPLASH_LOGO_DISPLAY}',
            '{SPLASH_LOGO_IMAGE}',
            '{SPLASH_BACKGROUND_IMAGE}',
            '{ONESIGNAL_APP_ID}',
            '{ONESIGNAL_ENABLED_ANDROID}',
            '{ONESIGNAL_SIGN}',
            '{DRAWER_MODE}',
            '{DRAWER_BACKGROUND_IMAGE}',
            '{DRAWER_LOGO_IMAGE}',
            '{DRAWER_BACKGROUND_COLOR}',
            '{DRAWER_THEME}',
            '{DRAWER_TITLE}',
            '{DRAWER_SUBTITLE}',
            '{DRAWER_DISPLAY_LOGO}',
            '{LOCAL_VALUE_1}',
            '{LOCAL_VALUE_2}',
            '{LOCAL_VALUE_3}',
            '{LOCAL_VALUE_4}',
            '{LOCAL_OFFLINE_IMAGE}',
            '{LOCAL_VALUE_5}',
            '{LOCAL_VALUE_6}',
            '{LOCAL_ERROR_IMAGE}',
            '{LOCAL_VALUE_7}',
            '{LOCAL_VALUE_8}',
            '{BAR_NAVIGATION_ARRAY}',
            '{MAIN_NAVIGATION_ARRAY}',
            '{MODAL_NAVIGATION_ARRAY}',
            '{APP_ACTIVE_COLOR}',
            '{APP_ICON_COLOR}'
        ];
        $configCodeVariable = [
            // File generated date
            date('d-m-Y H:i:s'),
            // App UID
            $app["uid"],
            // API server url
            env("app.baseURL"),
            // App name
            $app["name"],
            // App link
            $app["link"],
            // Display page name without app name
            !$app["display_title"] ? "true" : "false",
            // App main color
            $app["color_theme"],
            // Title color
            !$app["color_title"] ? "true" : "false",
            // Enabled pull to refresh
            $app["pull_to_refresh"] ? "false" : "true",
            // User agent
            $app["user_agent"],
            // App version
            $version,
            // Admin email
            $app["email"],
            // Layout template (typed value)
            $this->dartTypes->get_layout($app["template"]),
            // Loader page mode (typed value)
            $this->dartTypes->get_loader_mode($app["loader"]),
            // Loader color
            $app["loader_color"],
            // Access camera
            !$app["camera"] ? "false" : "true",
            // Access microphone
            !$app["microphone"] ? "false" : "true",
            // Access geolocation
            !$app["gps"] ? "false" : "true",
            // CSS divs for hide in app
            $this->dartTypes->get_css_block($styles),
            // Splashscreen mode
            !$splashscreen["background"] ? "false" : "true",
            // Splashscreen background color
            $splashscreen["color"],
            // Splashscreen tagline
            $splashscreen["tagline"],
            // Splashscreen text theme
            !$splashscreen["theme"] ? "#ffffff" : "#000000",
            // Minimum splashscreen delay
            (int) $splashscreen["delay"],
            // Display logo in splashscreen?
            !$splashscreen["use_logo"] ? "true" : "false",
            // Splashscreen logo image
            'splash_logo.png',
            // Splashscreen background image
            'splash_screen.png',
            // OneSignal APP ID
            $onesignal["android_enabled"] ? $encrypter->decrypt($onesignal['os_app_id']) : "",
            // Enabled android OneSignal
            !$onesignal["android_enabled"] ? "false" : "true",
            // Hash for create player ID header sign
            $onesignal["sign_key"],
            // Drawer mode
            $this->dartTypes->get_drawer_mode($drawer["mode"]),
            // Drawer background image
            'drawer_background.png',
            // Drawer logo image
            'logo.png',
            // Drawer background color
            $drawer["color"],
            // Drawer theme
            !$drawer["theme"] ? "true" : "false",
            // Drawer title
            $drawer["title"],
            // Drawer subtitle
            $drawer["subtitle"],
            // Drawer display logo
            !$drawer["logo_enabled"] ? "true" : "false",
            // Go back
            $local["string_1"],
            // Confirm title
            $local["string_2"],
            // Yes
            $local["string_3"],
            // No
            $local["string_4"],
            // Offline image
            'wifi.png',
            // Error internet connection (offline)
            $local["string_5"],
            // Error open web page
            $local["string_6"],
            // Error image
            'error.png',
            // Message about exit from app (Android)
            $local["string_7"],
            // Contact us email (About screen)
            $local["string_8"],
            // Bar navigation
            $this->dartTypes->get_navigation_items($bar_navigation),
            // Main navigation
            $this->dartTypes->get_navigation_items($main_navigation),
            // Modal navigation
            $this->dartTypes->get_navigation_items($modal_navigation),
            // App active color
            $app["active_color"],
            // App icon color
            $app["icon_color"]
        ];
        // replace
        $content = str_replace($configFileVariables, $configCodeVariable, $configFile);
        // github commit
        return $this->github->create_commit($app["uid"], "lib/config/app.dart", $content);
    }
}