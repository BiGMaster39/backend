<?php namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Libraries\Common;
use ReflectionException;

define("LIMIT", 20);

class App extends PrivateController
{
    private $apps;
    private $common;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->common = new Common();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list all apps
     * @param int $offset
     * @return mixed
     */
    public function list(int $offset = 0)
    {
        $apps = $this->apps
            ->orderBy("id", "DESC")
            ->where("deleted_at", 0)
            ->findAll(LIMIT, $offset);
        $count = $this->apps->countAllResults();
        $list = [];
        foreach ($apps as $item) {
            $list[] = [
                "created" => date('d-m-Y H:i', $item['created_at']),
                "name"    => $item["name"],
                "uid"     => $item["uid"],
                "link"    => $item["link"],
                "status"  => (int) $item["status"],
                "icon"    => $this->get_icon($item["uid"])
            ];
        }
        return $this->respond([
            "code" => 200,
            "apps" => $list,
            "count" => (int) $count
        ], 200);
    }

    /**
     * Get list apps for user ID
     * @param int $offset
     * @param int $userID
     * @return mixed
     */
    public function list_user(int $offset = 0, int $userID = 0)
    {
        $apps = $this->apps
            ->where("user", $userID)
            ->orderBy("id", "DESC")
            ->findAll(LIMIT, $offset);
        $count = $this->apps
            ->where("user", $userID)
            ->countAllResults();
        $list = [];
        foreach ($apps as $item) {
            $list[] = [
                "created" => date('d-m-Y H:i', $item['created_at']),
                "name"    => $item["name"],
                "uid"     => $item["uid"],
                "link"    => $item["link"],
                "status"  => (int) $item["status"],
                "icon"    => $this->get_icon($item["uid"])
            ];
        }
        return $this->respond([
            "code" => 200,
            "apps" => $list,
            "count" => (int) $count
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
            ->where(["uid" => esc($uid), "deleted_at" => 0])
            ->select("name,link,balance,uid,user")
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
                "user_id" => (int) $app["user"]
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
            ->where(["uid" => esc($uid)])
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
            ->where(["uid" => esc($uid)])
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

    /**
     * Update app balance
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function update_balance(string $uid = "")
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
        if (!$this->validate($this->balance_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $this->apps->update($app["id"], [
            "status"  => 1,
            "balance" => (int) $this->request->getPost("balance")
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**
     * Safe remove app
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function remove_app(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "deleted_at" => 0])
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
        $this->apps->update($app["id"], [
            "deleted_at" => time()
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for update builds app
     * @return array
     */
    private function balance_validation_type(): array
    {
        return [
            "balance" => ["label" => lang("Fields.field_137"),  "rules" => "required|numeric"],
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
}