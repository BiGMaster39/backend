<?php namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\SplashScreensModel;
use App\Libraries\Common;
use App\Libraries\GitHub;
use CodeIgniter\Config\Services;
use ReflectionException;

class Assets extends PrivateController
{
    private $apps;
    private $splash;
    private $common;
    private $github;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->splash = new SplashScreensModel();
        $this->common = new Common();
        $this->github = new GitHub();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get splashscreen detail
     * @param string $uid
     * @return mixed
     */
    public function splashscreen(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid)])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $detail = $this->splash
            ->where("app_id", $app["id"])
            ->first();
        return $this->respond([
            "code"   => 200,
            "detail" => [
                "background_mode" => (int) $detail["background"],
                "color"           => $detail["color"],
                "image"           => $detail["image"],
                "tagline"         => $detail["tagline"],
                "delay"           => (int) $detail["delay"],
                "theme"           => (int) $detail["theme"],
                "use_logo"        => (int) $detail["use_logo"],
                "background"      => !$detail["image"]
                    ? null
                    : base_url('upload/splash/'.$app['uid'].'/'.$detail["image"]),
                "logo"            => !$detail["logo"]
                    ? null
                    : base_url('upload/logos/'.$app['uid'].'/'.$detail["logo"])
            ],
        ], 200);
    }

    /**
     * Get app icons
     * @param string $uid
     * @return mixed
     */
    public function icons(string $uid = "")
    {
        helper('filesystem');
        $app = $this->apps
            ->where(["uid" => esc($uid)])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $isUploaded = is_dir(ROOTPATH.'upload/icons/'.$app['uid']);
        if ($isUploaded) {
            $android_icons = directory_map(ROOTPATH.'upload/icons/'.$app['uid']."/android", FALSE, TRUE);
            $ios_icons = directory_map(ROOTPATH.'upload/icons/'.$app['uid']."/ios", FALSE, TRUE);
            $url = base_url("upload/icons/".$app["uid"]);
        } else {
            $android_icons = [];
            $ios_icons = [];
            $url = base_url("upload/android/icons");
        }
        return $this->respond([
            "code"  => 200,
            "icons" => [
                "android" => $android_icons,
                "ios"     => $ios_icons,
                "upload"  => $isUploaded
            ],
            "url"   => $url,
            "unix"  => strtotime(date('m/d/Y h:i:s a', time())),
        ], 200);
    }

    /**
     * Download icon
     * @param string $uid
     * @param string $icon
     * @param string $platform
     * @return mixed
     */
    public function download_icon(string $uid = "", string $icon = "", string $platform = "") {
        $app = $this->apps
            ->where(["uid" => esc($uid)])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $os = $platform == "android" ? "android" : "ios";
        $file_url = base_url("upload/icons/".$app["uid"]."/".$os."/".$icon);
        header('Content-Type: image/png');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"" . $icon . "\"");
        return readfile($file_url);
    }

    /**
     * Update splashscreen settings
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function update_splash(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid)])
            ->select("id")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $screen = $this->splash
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_splash_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $color = esc($this->request->getPost("color"));
        if (!$this->common->hex_validation($color)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_12")
                ],
            ], 400);
        }
        $this->splash->update($screen["id"], [
            "background" => (int) $this->request->getPost("background_mode"),
            "color"      => esc($color),
            "tagline"    => esc($this->request->getPost("tagline")),
            "delay"      => (int) $this->request->getPost("delay"),
            "theme"      => (int) $this->request->getPost("theme"),
            "use_logo"   => (int) $this->request->getPost("use_logo"),
        ]);
        return $this->respond([
            "code"   => 200,
        ], 200);
    }

    /**
     * Upload splashscreen
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_splash(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid)])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $screen = $this->splash
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_splash_image_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        if ( !is_dir( ROOTPATH.'upload/splash/'.$app['uid'] ) ) {
            mkdir(ROOTPATH.'upload/splash/'.$app['uid'], 0777, true);
        }
        $image = $this->request->getFile('screen');
        $name = $image->getRandomName();
        $image->move(ROOTPATH.'upload/splash/'.$app['uid'], $name);
        $git_result = $this->github->create_commit(
            $app["uid"],
            "assets/app/splash_screen.png",
            file_get_contents(ROOTPATH.'upload/splash/'.$app['uid'].'/'.$name)
        );
        if (!$git_result) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $git_result["message"]["error"]
                ],
            ], 502);
        }
        $this->splash->update($screen["id"], [
            "image" => $name
        ]);
        return $this->respond([
            "code" => 200,
            "uri"  => base_url('upload/splash/'.$app['uid'].'/'.$name)
        ], 200);
    }

    /**
     * Upload logo
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_logo(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid)])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $screen = $this->splash
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_logo_image_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $image = $this->request->getFile('logo');
        $name = $image->getRandomName();
        $image->move(ROOTPATH.'upload/logos/'.$app['uid'], $name);
        $git_result = $this->github->create_commit(
            $app["uid"],
            "assets/app/splash_logo.png",
            file_get_contents(ROOTPATH.'upload/logos/'.$app['uid'].'/'.$name)
        );
        if (!$git_result["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $git_result["message"]["error"]
                ],
            ], 502);
        }
        $this->splash->update($screen["id"], [
            "logo" => $name
        ]);
        return $this->respond([
            "code" => 200,
            "uri"  => base_url('upload/logos/'.$app['uid'].'/'.$name)
        ], 200);
    }

    /**
     * Upload icon
     * @param string $uid
     * @return mixed
     */
    public function upload_icon(string $uid = "")
    {
        helper('filesystem');
        $app = $this->apps
            ->where(["uid" => esc($uid)])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        if (!$this->validate($this->update_icon_image_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $image = $this->request->getFile('icon');
        list($width, $height) = getimagesize($image->getTempName());
        if ($width != 1024 || $height != 1024) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_19")
                ],
            ], 400);
        }
        $this->clean_folder($app['uid']);
        // save original image
        $image->move(ROOTPATH.'upload/icons/'.$app['uid'], "original.png");
        // create icon collection
        $android_sizes = $this->android_icons_rules();
        foreach ($android_sizes as $key => $item) {
            $name = $key."_".$item["width"].".png";
            $this->createAssetApp(
                ROOTPATH.'upload/icons/'.$app['uid']."/original.png",
                $item["width"],
                ROOTPATH.'upload/icons/'.$app['uid']."/android/".$name,
                ROOTPATH.'upload/icons/'.$app['uid']."/android"
            );
        }
        $ios_sizes = $this->ios_icons_rules();
        foreach ($ios_sizes as $key => $item) {
            $name = $key.".png";
            $this->createAssetApp(
                ROOTPATH.'upload/icons/'.$app['uid']."/original.png",
                $item["width"],
                ROOTPATH.'upload/icons/'.$app['uid']."/ios/".$name,
                ROOTPATH.'upload/icons/'.$app['uid']."/ios"
            );
        }
        $git_result = $this->commit_icons($app["uid"]);
        if (!$git_result["event"]) {
            delete_files(ROOTPATH.'upload/icons/'.$app['uid']);
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $git_result["message"]["error"]
                ],
            ], 502);
        }
        return $this->respond([
            "code"    => 200,
            "icons"   => [
                "android" => directory_map(ROOTPATH.'upload/icons/'.$app['uid']."/android", FALSE, TRUE),
                "ios"     => directory_map(ROOTPATH.'upload/icons/'.$app['uid']."/ios", FALSE, TRUE),
            ],
            "url"     => base_url("upload/icons/".$app["uid"]),
            "unix"    => strtotime(date('m/d/Y h:i:s a', time())),
            "preview" => base_url("upload/icons/".$app['uid']."/android/hdpi_72.png")
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for update splashscreen settings
     * @return array
     */
    private function update_splash_validation_type(): array
    {
        return [
            "background_mode" => ["label" => lang("Fields.field_23"),  "rules" => "required|in_list[0,1]"],
            "color"           => ["label" => lang("Fields.field_24"),  "rules" => "required|min_length[7]|max_length[7]"],
            "tagline"         => ["label" => lang("Fields.field_25"),  "rules" => "max_length[40]"],
            "delay"           => ["label" => lang("Fields.field_26"),  "rules" => "required|numeric|is_natural"],
            "theme"           => ["label" => lang("Fields.field_27"),  "rules" => "required|in_list[0,1]"],
            "use_logo"        => ["label" => lang("Fields.field_28"),  "rules" => "required|in_list[0,1]"],
        ];
    }

    /**
     * Get validation rules for upload splashscreen image background
     * @return array
     */
    private function update_splash_image_validation_type(): array
    {
        return [
            'screen' => ['label' => lang("Fields.field_29"), 'rules' => 'uploaded[screen]|max_size[screen,500]|ext_in[screen,png,jpg]|max_dims[screen,2436,2436]'],
        ];
    }

    /**
     * Get validation rules for upload logo image
     * @return array
     */
    private function update_logo_image_validation_type(): array
    {
        return [
            'logo' => ['label' => lang("Fields.field_30"), 'rules' => 'uploaded[logo]|max_size[logo,500]|ext_in[logo,png,jpg]|max_dims[logo,1200,1200]'],
        ];
    }

    /**
     * Get validation rules for upload icon image
     * @return array
     */
    private function update_icon_image_validation_type(): array
    {
        return [
            'icon' => ['label' => lang("Fields.field_22"), 'rules' => 'uploaded[icon]|max_size[icon,1200]|ext_in[icon,png]|max_dims[icon,1024,1024]'],
        ];
    }

    /**
     * Create image assets
     * @param string $original
     * @param int $size
     * @param string $final
     * @param string $new_path
     * @return void
     */
    private function createAssetApp(string $original, int $size, string $final, string $new_path)
    {
        if ( !is_dir( $new_path ) ) {
            mkdir($new_path, 0777, true);
        }
        Services::image()
            ->withFile($original)
            ->fit($size, $size, 'center')
            ->save($final, 100);
    }

    /**
     * Remove all files in icons folder
     * @param string $uid
     * @return void
     */
    private function clean_folder(string $uid)
    {
        helper('filesystem');
        delete_files(ROOTPATH.'upload/icons/'.$uid, true);
    }

    /**
     * Upload app icons to Github repo
     * @param string $uid
     * @return array
     */
    private function commit_icons(string $uid): array
    {
        helper('filesystem');
        // ios icons
        $icons = directory_map(ROOTPATH.'upload/icons/'.$uid."/ios", FALSE, TRUE);
        foreach ($icons as $icon) {
            $content = file_get_contents(ROOTPATH.'upload/icons/'.$uid.'/ios/'.$icon);
            $result = $this->github->create_commit($uid, 'ios/Runner/Assets.xcassets/AppIcon.appiconset/'.$icon, $content);
            if (!$result["event"]) {
                return $result;
            }
        }
        // android
        $icons = [];
        $icons[] = [
            "name" => "mipmap-hdpi",
            "path" => ROOTPATH.'upload/icons/'.$uid.'/android/hdpi_72.png'
        ];
        $icons[] = [
            "name" => "mipmap-mdpi",
            "path" => ROOTPATH.'upload/icons/'.$uid.'/android/mdpi_48.png'
        ];
        $icons[] = [
            "name" => "mipmap-xhdpi",
            "path" => ROOTPATH.'upload/icons/'.$uid.'/android/xhdpi_96.png'
        ];
        $icons[] = [
            "name" => "mipmap-xxhdpi",
            "path" => ROOTPATH.'upload/icons/'.$uid.'/android/xxhdpi_144.png'
        ];
        $icons[] = [
            "name" => "mipmap-xxxhdpi",
            "path" => ROOTPATH.'upload/icons/'.$uid.'/android/xxxhdpi_192.png'
        ];
        foreach ($icons as $icon) {
            $content = file_get_contents($icon["path"]);
            $result = $this->github->create_commit($uid, 'android/app/src/main/res/'.$icon["name"].'/ic_launcher.png', $content);
            if (!$result["event"]) {
                return $result;
            }
        }
        return [
            "event" => true
        ];
    }

    /**
     * Get sizes array for android icon
     * @return array
     */
    private function android_icons_rules(): array
    {
        return [
            "mdpi" => [
                "width"  => 48,
                "height" => 48
            ],
            "hdpi" => [
                "width"  => 72,
                "height" => 72
            ],
            "xhdpi" => [
                "width"  => 96,
                "height" => 96
            ],
            "xxhdpi" => [
                "width"  => 144,
                "height" => 144
            ],
            "xxxhdpi" => [
                "width"  => 192,
                "height" => 192
            ],
        ];
    }

    /**
     * Get sizes array for iOS icon
     * @return array
     */
    private function ios_icons_rules(): array
    {
        return [
            "20" => [
                "width"  => 20,
                "height" => 20
            ],
            "29" => [
                "width"  => 29,
                "height" => 29
            ],
            "40" => [
                "width"  => 40,
                "height" => 40
            ],
            "50" => [
                "width"  => 50,
                "height" => 50
            ],
            "57" => [
                "width"  => 57,
                "height" => 57
            ],
            "58" => [
                "width"  => 58,
                "height" => 58
            ],
            "60" => [
                "width"  => 60,
                "height" => 60
            ],
            "72" => [
                "width"  => 72,
                "height" => 72
            ],
            "76" => [
                "width"  => 76,
                "height" => 76
            ],
            "80" => [
                "width"  => 80,
                "height" => 80
            ],
            "87" => [
                "width"  => 87,
                "height" => 87
            ],
            "100" => [
                "width"  => 100,
                "height" => 100
            ],
            "114" => [
                "width"  => 114,
                "height" => 114
            ],
            "120" => [
                "width"  => 120,
                "height" => 120
            ],
            "144" => [
                "width"  => 144,
                "height" => 144
            ],
            "152" => [
                "width"  => 152,
                "height" => 152
            ],
            "167" => [
                "width"  => 167,
                "height" => 167
            ],
            "180" => [
                "width"  => 180,
                "height" => 180
            ],
            "1024" => [
                "width"  => 1024,
                "height" => 1024
            ],
        ];
    }
}