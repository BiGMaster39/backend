<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\DrawersModel;
use App\Models\LocalsModel;
use App\Models\PushModel;
use App\Models\SplashScreensModel;
use App\Libraries\Uid;
use App\Libraries\Common;
use App\Libraries\GitHub;
use ReflectionException;

define("LIMIT", 20);

class Apps extends PrivateController
{
    private $apps;
    private $drawers;
    private $locals;
    private $push;
    private $splash;
    private $uid;
    private $common;
    private $github;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->drawers = new DrawersModel();
        $this->locals = new LocalsModel();
        $this->push = new PushModel();
        $this->splash = new SplashScreensModel();
        $this->uid = new Uid();
        $this->common = new Common();
        $this->github = new GitHub();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list all apps
     * @param int $order
     * 0 - all apps
     * 1 - draft apps
     * 2 - active apps
     * @param int $offset
     * @return mixed
     */
    public function list(int $order = 0, int $offset = 0)
    {
        if ($order == 0) {
            $where = ["user" => $this->user["id"], "deleted_at" => 0];
        } elseif ($order == 1) {
            $where = ["user" => $this->user["id"], "status" => 0, "deleted_at" => 0];
        } else {
            $where = ["user" => $this->user["id"], "status >" => 0, "deleted_at" => 0];
        }
        $apps = $this->apps
            ->where($where)
            ->orderBy("id", "DESC")
            ->findAll(LIMIT, $offset);
        $count = $this->apps
            ->where($where)
            ->countAllResults();
        return $this->respond([
            "code"  => 200,
            "apps"  => $this->build_results($apps),
            "count" => (int) $count
        ], 200);
    }

    /**
     * Create new app
     * @return mixed
     * @throws ReflectionException
     */
    public function create()
    {
        if (!$this->validate($this->create_validation_type())) {
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
        $link = esc($this->request->getPost("link"));
        if (!$this->common->uri_validation($link)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_13")
                ],
            ], 400);
        }
        $uid = $this->uid->create();
        $template = (int) $this->request->getPost("template");
        $id = $this->apps->insert([
            "uid"          => $uid,
            "name"         => esc($this->request->getPost("name")),
            "user"         => $this->user["id"],
            "status"       => 0,
            "link"         => $link,
            "color_theme"  => $color,
            "color_title"  => (int) $this->request->getPost("theme"),
            "template"     => $template,
            "email"        => $this->user["email"],
            "language"     => strtoupper($this->request->getLocale()),
            "loader_color" => $color,
            "app_id"       => $this->create_app_id($link),
            "icon_color"   => $color,
            "active_color" => $color,
        ]);
        $git_result = $this->github->create_branch($uid);
        if (!$git_result["event"]) {
            $this->apps->delete($id);
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $git_result["message"]["error"]
                ],
            ], 400);
        }
        $this->create_app_settings($id, $template, $color);
        return $this->respond([
            "code" => 200,
            "uid"  => $uid
        ], 200);
    }

    /**
     * Get short app detail
     * @param string $uid
     * @return mixed
     */
    public function short(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"], "deleted_at" => 0])
            ->select("name,link,balance,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        return $this->respond([
            "code" => 200,
            "app"  => [
                "name"    => $app["name"],
                "link"    => $app["link"],
                "balance" => (int) $app["balance"],
                "icon"    => $this->get_icon($app["uid"]),
                "uid"     => $app["uid"],
                "user_id" => 0
            ]
        ], 200);
    }

    /**
     * Get main app detail
     * @param string $uid
     * @return mixed
     */
    public function detail(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
            ->select("name,link,app_id,user_agent,orientation,status,gps,language,email,gps,camera,microphone")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        return $this->respond([
            "code" => 200,
            "app"  => [
                "name"        => $app["name"],
                "link"        => $app["link"],
                "app_id"      => $app["app_id"],
                "user_agent"  => $app["user_agent"],
                "orientation" => (int) $app["orientation"],
                "status"      => (int) $app["status"],
                "language"    => $app["language"],
                "email"       => $app["email"],
                "permissions" => [
                    "gps"        => (int) $app["gps"],
                    "camera"     => (int) $app["camera"],
                    "microphone" => (int) $app["microphone"],
                ]
            ]
        ], 200);
    }

    /**
     * Update main app detail
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function update(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
            ->select("id,link,status,app_id")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        if (!$this->validate($this->update_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $app_id = esc($this->request->getPost("app_id"));
        if ($this->common->uri_validation($app_id)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => "1".lang("Message.message_15")
                ],
            ], 400);
        }
        if (count(explode('.', $app_id)) != 3) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_15")
                ],
            ], 400);
        }
        $link = esc($this->request->getPost("link"));
        if (!$this->common->uri_validation($link)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_13")
                ],
            ], 400);
        }
        $this->apps->update($app["id"], [
            "name"        => esc($this->request->getPost("name")),
            "link"        => !$app["status"] ? $link : $app["link"],
            "orientation" => (int) $this->request->getPost("orientation"),
            "app_id"      => !$app["status"] ? esc($app_id) : $app["app_id"],
            "user_agent"  => esc($this->request->getPost("user_agent")),
            "language"    => strtoupper(
                esc($this->request->getPost("language"))
            ),
            "email"       => esc($this->request->getPost("email")),
            "gps"         => (int) $this->request->getPost("gps"),
            "camera"      => (int) $this->request->getPost("camera"),
            "microphone"  => (int) $this->request->getPost("microphone"),
        ]);
        return $this->respond([
            "code" => 200
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for create new app
     * @return array
     */
    private function create_validation_type(): array
    {
        return [
            "link"      => ["label" => lang("Fields.field_7"),  "rules" => "required|min_length[3]|max_length[250]"],
            "name"      => ["label" => lang("Fields.field_6"),  "rules" => "required|min_length[3]|max_length[50]"],
            "template"  => ["label" => lang("Fields.field_10"), "rules" => "required|in_list[0,1,2,3]"],
            "color"     => ["label" => lang("Fields.field_8"),  "rules" => "required|min_length[7]|max_length[7]"],
            "theme"     => ["label" => lang("Fields.field_9"),  "rules" => "required|in_list[0,1]"],
        ];
    }

    /**
     * Get validation rules for create new app
     * @return array
     */
    private function update_validation_type(): array
    {
        return [
            "link"        => ["label" => lang("Fields.field_7"),  "rules" => "required|min_length[3]|max_length[250]"],
            "name"        => ["label" => lang("Fields.field_6"),  "rules" => "required|min_length[3]|max_length[50]"],
            "app_id"      => ["label" => lang("Fields.field_11"), "rules" => "required|min_length[3]|max_length[50]"],
            "user_agent"  => ["label" => lang("Fields.field_12"), "rules" => "max_length[200]"],
            "orientation" => ["label" => lang("Fields.field_13"), "rules" => "required|in_list[0,1,2]"],
            "language"    => ["label" => lang("Fields.field_40"), "rules" => "required|min_length[2]|max_length[2]"],
            "email"       => ["label" => lang("Fields.field_43"), "rules" => "required|min_length[3]|max_length[70]|valid_email"],
            "gps"         => ["label" => lang("Fields.field_39"), "rules" => "required|in_list[0,1]"],
            "camera"      => ["label" => lang("Fields.field_41"), "rules" => "required|in_list[0,1]"],
            "microphone"  => ["label" => lang("Fields.field_42"), "rules" => "required|in_list[0,1]"],
        ];
    }

    /**
     * Build apps list result for display
     * @param array $list
     * @return array
     */
    private function build_results(array $list): array
    {
        $apps = [];
        foreach ($list as $item) {
            $apps[] = [
                "created" => date('d-m-Y H:i', $item['created_at']),
                "name"    => $item["name"],
                "uid"     => $item["uid"],
                "link"    => $item["link"],
                "status"  => (int) $item["status"],
                "icon"    => $this->get_icon($item["uid"])
            ];
        }
        return $apps;
    }

    /**
     * Get app icon
     * @param string $uid
     * @return string|null
     */
    private function get_icon(string $uid): ?string
    {
        $isIcon = is_dir(ROOTPATH.'upload/icons/'.$uid);
        if ($isIcon) {
            $unix = strtotime(date('m/d/Y h:i:s a', time()));
            $icon = base_url("upload/icons/".$uid."/android/hdpi_72.png?".$unix);
        } else {
            $icon = null;
        }
        return $icon;
    }

    /**
     * Create app settings
     * @param string $id
     * @param int $template
     * @param string $color
     * @return void
     * @throws ReflectionException
     */
    private function create_app_settings(string $id, int $template, string $color)
    {
        $this->drawers->insert([
            "app_id" => $id,
            "color"  => $color,
            "mode"   => !$template ? 1 : 0
        ]);
        $this->locals->insert([
            "app_id"   => $id,
            "string_1" => lang("Fields.field_53"),
            "string_2" => lang("Fields.field_54"),
            "string_3" => lang("Fields.field_55"),
            "string_4" => lang("Fields.field_56"),
            "string_5" => lang("Fields.field_57"),
            "string_6" => lang("Fields.field_58"),
            "string_7" => lang("Fields.field_59"),
            "string_8" => lang("Fields.field_60"),
        ]);
        $this->push->insert([
            "app_id"   => $id,
            "sign_key" => hash('sha256', $this->uid->create())
        ]);
        $this->splash->insert([
            "app_id" => $id,
            "delay"  => 3,
            "color"  => $color
        ]);
    }

    /**
     * Create app id
     * @param $link
     * @return string
     */
    private function create_app_id($link): string
    {
        $host = $this->common->get_site_host($link);
        $site = explode('.', $host);
        if (count($site) > 2) {
            return $site[2].'.'.$site[1].'.'.$site[0];
        } else {
            return 'app.'.$site[0].'.'.$site[1];
        }
    }
}