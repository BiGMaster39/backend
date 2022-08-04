<?php namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\NavigationModel;
use App\Libraries\Common;
use App\Libraries\GitHub;
use ReflectionException;

class Navigation extends PrivateController
{
    private $apps;
    private $navigation;
    private $common;
    private $github;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->navigation = new NavigationModel();
        $this->common = new Common();
        $this->github = new GitHub();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get all navigation items
     * @param string $uid
     * @return mixed
     */
    public function list(string $uid = "")
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
        $list = $this->navigation
            ->where("app_id", $app["id"])
            ->findAll();
        $count = $this->navigation
            ->where("app_id", $app["id"])
            ->countAllResults();
        $navigation = [];
        foreach ($list as $item) {
            $navigation[] = [
                "id"   => (int) $item["id"],
                "name" => $item["name"],
                "type" => (int) $item["type"],
                "icon" => !$item["icon"] ? null : $item["icon"],
                "link" => $item["link"]
            ];
        }
        return $this->respond([
            "code"  => 200,
            "list"  => $navigation,
            "count" => $count
        ], 200);
    }

    /**
     * Create navigation item
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function create(string $uid = "")
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
        if (!$this->validate($this->create_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $action_type = (int) $this->request->getPost("action_type");
        $link = esc($this->request->getPost("link"));
        // link validation
        if ($action_type == 0 || $action_type == 1) {
            if (!$this->common->uri_validation($link)) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_13")
                    ],
                ], 400);
            }
        }
        // email validation
        if ($action_type == 3) {
            if (!$this->common->email_validation($link)) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_18")
                    ],
                ], 400);
            }
        }
        // phone validation
        if ($action_type == 4) {
            if (!is_numeric($link)) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_62")
                    ],
                ], 400);
            }
        }
        $icon = esc($this->request->getPost("icon"));
        if (!file_exists(ROOTPATH.'icons/catalog/'.$icon.'.svg')) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_32")
                ],
            ], 400);
        }
        if (!$this->common->icon_ex_validation($app["id"], $icon)) {
            $git_result = $this->github->upload_commit(
                $app["uid"],
                'assets/app/'.$icon.'.svg',
                file_get_contents(ROOTPATH.'icons/catalog/'.$icon.'.svg')
            );
            if (!$git_result["event"]) {
                return $this->respond([
                    "code"    => 502,
                    "message" => [
                        "error" => $git_result["message"]["error"]
                    ],
                ], 502);
            }
        }
        $id = $this->navigation->insert([
            "name"    => esc($this->request->getPost("name")),
            "app_id"  => $app["id"],
            "type"    => $action_type,
            "link"    => $link,
            "icon"    => esc($this->request->getPost("icon"))
        ]);
        return $this->respond([
            "code" => 200,
            "id"   => (int) $id
        ], 200);
    }

    /**
     * Update navigation item
     * @param int $id
     * @return mixed
     * @throws ReflectionException
     */
    public function update(int $id = 0)
    {
        $item = $this->navigation
            ->where("id", $id)
            ->select("id,app_id,icon")
            ->first();
        if (!$item) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_17")
                ],
            ], 400);
        }
        $app = $this->apps
            ->where(["id" => $item["app_id"]])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_17")
                ],
            ], 400);
        }
        if (!$this->validate($this->create_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $action_type = (int) $this->request->getPost("action_type");
        $link = esc($this->request->getPost("link"));
        if ($action_type == 0 || $action_type == 1) {
            if (!$this->common->uri_validation($link)) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_13")
                    ],
                ], 400);
            }
        }
        if ($action_type == 3) {
            if (!is_numeric($link)) {
                if (!$this->common->email_validation($link)) {
                    return $this->respond([
                        "code"    => 400,
                        "message" => [
                            "error" => lang("Message.message_18")
                        ],
                    ], 400);
                }
            }
        }
        $icon = esc($this->request->getPost("icon"));
        if ($icon != $item["icon"]) {
            if (!file_exists(ROOTPATH.'icons/catalog/'.$icon.'.svg')) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_32")
                    ],
                ], 400);
            }
            if (!$this->common->icon_ex_validation($app["id"], $icon)) {
                $git_result = $this->github->upload_commit(
                    $app["uid"],
                    'assets/app/'.$icon.'.svg',
                    file_get_contents(ROOTPATH.'icons/catalog/'.$icon.'.svg')
                );
                if (!$git_result["event"]) {
                    return $this->respond([
                        "code"    => 502,
                        "message" => [
                            "error" => $git_result["message"]["error"]
                        ],
                    ], 502);
                }
            }
            if ($this->common->is_remove_icon($app["id"], $item["icon"])) {
                $del_result = $this->github->delete_file(
                    $app["uid"],
                    'assets/app/'.$item["icon"].'.svg',
                );
                if (!$del_result) {
                    return $this->respond([
                        "code"    => 502,
                        "message" => [
                            "error" => $git_result["message"]["error"]
                        ],
                    ], 502);
                }
            }
        }
        $this->navigation->update($item["id"], [
            "name"    => esc($this->request->getPost("name")),
            "type"    => $action_type,
            "link"    => $link,
            "icon"    => esc($this->request->getPost("icon"))
        ]);
        return $this->respond([
            "code" => 200,
            "id"   => (int) $item["id"]
        ], 200);
    }

    /**
     * Remove navigation item
     * @param int $id
     * @return mixed
     */
    public function remove(int $id = 0)
    {
        $item = $this->navigation
            ->where("id", (int) $id)
            ->select("id,app_id,icon")
            ->first();
        if (!$item) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_17")
                ],
            ], 400);
        }
        $app = $this->apps
            ->where(["id" => $item["app_id"]])
            ->select("id,uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_17")
                ],
            ], 400);
        }
        if ($this->common->is_remove_icon($app["id"], $item["icon"])) {
            $git_result = $this->github->delete_file(
                $app["uid"],
                'assets/app/'.$item["icon"].'.svg',
            );
            if (!$git_result["event"]) {
                return $this->respond([
                    "code"    => 502,
                    "message" => [
                        "error" => $git_result["message"]["error"]
                    ],
                ], 502);
            }
        }
        $this->navigation->delete($item["id"]);
        return $this->respond([
            "code" => 200
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for create new navigation item
     * @return array
     */
    private function create_validation_type(): array
    {
        return [
            "name"         => ["label" => lang("Fields.field_19"),  "rules" => "required|min_length[3]|max_length[50]"],
            "action_type"  => ["label" => lang("Fields.field_20"),  "rules" => "required|in_list[0,1,2,3,4,5]"],
            "icon"         => ["label" => lang("Fields.field_73"),  "rules" => "required|min_length[3]|max_length[50]"],
        ];
    }
}