<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\BarNavigationModel;
use App\Models\DrawersModel;
use App\Models\NavigationModel;
use App\Models\ModalNavigationModel;
use App\Models\SplashScreensModel;
use App\Models\StylesModel;

class Preview extends PrivateController
{
    private $apps;
    private $styles;
    private $drawers;
    private $splash;
    private $app_navigation;
    private $bar_navigation;
    private $modal_navigation;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->styles = new StylesModel();
        $this->drawers = new DrawersModel();
        $this->splash = new SplashScreensModel();
        $this->app_navigation = new NavigationModel();
        $this->bar_navigation = new BarNavigationModel();
        $this->modal_navigation = new ModalNavigationModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get config data fpr app preview
     * @param string $uid
     * @param string $mode
     * @return mixed
     */
    public function get(string $uid = "", string $mode = "app")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $divs = $this->styles
            ->where("app_id", $app["id"])
            ->findAll();
        $styles = [];
        foreach ($divs as $div) {
            $styles[] = $div["name"];
        }
        $drawer = $this->drawers
            ->where("app_id", $app["id"])
            ->first();
        $splash = $this->splash
            ->where("app_id", $app["id"])
            ->first();
        $main_navs = $this->app_navigation
            ->where("app_id", $app["id"])
            ->findAll();
        $main_navigation = [];
        foreach ($main_navs as $nav) {
            $main_navigation[] = [
                "icon"   => $nav["icon"],
                "name"   => $nav["name"],
                "action" => [
                    "type"  => $this->get_action_type($nav["type"]),
                    "value" => $nav["link"]
                ]
            ];
        }
        $bar_navs = $this->bar_navigation
            ->where("app_id", $app["id"])
            ->findAll();
        $bar_navigation = [];
        foreach ($bar_navs as $nav) {
            $bar_navigation[] = [
                "icon"   => $nav["icon"],
                "name"   => $nav["name"],
                "action" => [
                    "type"  => $this->get_action_type($nav["type"]),
                    "value" => $nav["link"]
                ]
            ];
        }
        $modal_navs = $this->modal_navigation
            ->where("app_id", $app["id"])
            ->findAll();
        $modal_navigation = [];
        foreach ($modal_navs as $nav) {
            $modal_navigation[] = [
                "icon"   => $nav["icon"],
                "name"   => $nav["name"],
                "action" => [
                    "type"  => $this->get_action_type($nav["type"]),
                    "value" => $nav["link"]
                ]
            ];
        }
        $configFileVariables = [
            '{MODE}',
            '{APP_NAME}',
            '{APP_LINK}',
            '{DISPLAY_TITLE}',
            '{COLOR}',
            '{IS_DARK}',
            '{APP_TEMPLATE}',
            '{INDICATOR}',
            '{INDICATOR_COLOR}',
            '{CSS}',
            '{USER_AGENT}',
            '{DRAWER_TITLE}',
            '{DRAWER_SUBTITLE}',
            '{DRAWER_MODE}',
            '{DRAWER_BACKGROUND_COLOR}',
            '{DRAWER_IS_DARK}',
            '{DRAWER_BACKGROUND_IMAGE}',
            '{DRAWER_LOGO_IMAGE}',
            '{DRAWER_IS_DISPLAY_LOGO}',
            '{SPLASH_BACKGROUND_COLOR}',
            '{SPLASH_COLOR}',
            '{SPLASH_BACKGROUND_IMAGE}',
            '{SPLASH_TAGLINE}',
            '{SPLASH_LOGO}',
            '{SPLASH_IS_DISPLAY_LOGO}',
            '{SPLASH_IS_IMAGE_BACKGROUND}',
            '{MAIN_NAVIGATION}',
            '{BAR_NAVIGATION}',
            '{MODAL_NAVIGATION}',
            '{ACTIVE_COLOR}',
            '{ICON_COLOR}'
        ];
        $configCodeVariable = [
            $mode === "app" ? "app" : "splash",
            $app["name"],
            $app["link"],
            (int) $app["display_title"] ? 0: 1,
            $app["color_theme"],
            (int) !$app["color_title"] ? 1 : 0,
            (int) $app["template"],
            (int) $app["loader"],
            $app["loader_color"],
            json_encode($styles),
            $app["user_agent"],
            $drawer["title"],
            $drawer["subtitle"],
            (int) $drawer["mode"],
            $drawer["color"],
            !$drawer["theme"] ? 1 : 0,
            !$drawer["background"]
                ? base_url('writable/snack/static/drawer_background.png')
                : base_url('upload/drawer/'.$app['uid'].'/'.$drawer["background"]),
            !$drawer["logo"]
                ? base_url('writable/snack/static/logo.png')
                : base_url('upload/drawer/'.$app['uid'].'/'.$drawer["logo"]),
            !$drawer["logo_enabled"] ? 1 : 0,
            $splash["color"],
            !$splash["theme"] ? "#ffffff" : "#000000",
            !$splash["image"]
                ? base_url('writable/snack/static/splash_background.png')
                : base_url('upload/splash/'.$app['uid'].'/'.$splash["image"]),
            $splash["tagline"],
            !$splash["logo"]
                ? base_url('writable/snack/static/logo.png')
                : base_url('upload/logos/'.$app['uid'].'/'.$splash["logo"]),
            !$splash["use_logo"] ? 1 : 0,
            (int) $splash["background"],
            json_encode($main_navigation),
            json_encode($bar_navigation),
            json_encode($modal_navigation),
            $app["active_color"],
            $app["icon_color"]
        ];
        $config = str_replace(
            $configFileVariables,
            $configCodeVariable,
            file_get_contents(WRITEPATH.'snack/config.js')
        );
        return $this->respond([
            "code"   => 200,
            "config" => $config,
        ], 200);
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get nav action type
     * @param int $type
     * @return string
     */
    public function get_action_type(int $type) :string
    {
        switch($type)
        {
            case 0 :
                return "internal";
            case 1 :
                return "external";
            case 2 :
                return "share";
            case 3 :
                return "email";
            case 4 :
                return "phone";
            default :
                return "open_modal";
        }
    }
}