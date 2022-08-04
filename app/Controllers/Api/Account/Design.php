<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\StylesModel;
use App\Libraries\Common;
use ReflectionException;

class Design extends PrivateController
{
    private $apps;
    private $styles;
    private $common;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->styles = new StylesModel();
        $this->common = new Common();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get app template settings detail
     * @param string $uid
     * @return mixed
     */
    public function detail(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
            ->select("color_theme,color_title,template,loader,
            pull_to_refresh,loader_color,display_title,icon_color,active_color")
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
            "code"   => 200,
            "layout" => [
                "color_theme"     => $app["color_theme"],
                "color_title"     => (int) $app["color_title"],
                "template"        => (int) $app["template"],
                "loader"          => (int) $app["loader"],
                "pull_to_refresh" => (int) $app["pull_to_refresh"],
                "loader_color"    => $app["loader_color"],
                "display_title"   => (int) $app["display_title"],
                "icon_color"      => $app["icon_color"],
                "active_color"    => $app["active_color"]
            ]
        ], 200);
    }

    /**
     * Get styles div for hide in app
     * @param string $uid
     * @return mixed
     */
    public function styles(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
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
        $divs = $this->styles
            ->where("app_id", $app["id"])
            ->findAll();
        $result = [];
        foreach ($divs as $div) {
            $result[] = [
                "name" => $div["name"],
                "id"   => (int) $div["id"]
            ];
        }
        return $this->respond([
            "code"    => 200,
            "styles"  => $result,
            "loading" => false
        ], 200);
    }

    /**
     * Remove div
     * @param int $id
     * @return mixed
     */
    public function remove_div(int $id = 0)
    {
        $div = $this->styles
            ->where("id", (int) $id)
            ->first();
        if (!$div) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_24")
                ],
            ], 400);
        }
        $app = $this->apps
            ->where(["id" => $div["app_id"], "user" => $this->user["id"]])
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
        $this->styles->delete($div["id"]);
        return $this->respond([
            "code" => 200
        ], 200);
    }

    /**
     * Create div
     * @param String $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function create_div(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
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
        if (!$this->validate($this->create_div_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $name = esc($this->request->getPost("name"));
        $id = $this->styles->insert([
            "name"   =>  $name,
            "app_id" => $app["id"]
        ]);
        return $this->respond([
            "code" => 200,
            "id"   => (int) $id,
            "name" => $name
        ], 200);
    }

    /**
     * Update main settings
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function update_detail(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
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
        if (!$this->validate($this->update_detail_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $color_theme = esc($this->request->getPost("color_theme"));
        if (!$this->common->hex_validation($color_theme)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_12")
                ],
            ], 400);
        }
        $loader_color = esc($this->request->getPost("loader_color"));
        if (!$this->common->hex_validation($loader_color)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_16")
                ],
            ], 400);
        }
        $icon_color = esc($this->request->getPost("icon_color"));
        if (!$this->common->hex_validation($icon_color)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_67")
                ],
            ], 400);
        }
        $active_color = esc($this->request->getPost("active_color"));
        if (!$this->common->hex_validation($active_color)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_68")
                ],
            ], 400);
        }
        $this->apps->update($app["id"], [
            "color_theme"     => esc($color_theme),
            "color_title"     => (int) $this->request->getPost("color_title"),
            "template"        => (int) $this->request->getPost("template"),
            "loader"          => (int) $this->request->getPost("loader"),
            "pull_to_refresh" => (int) $this->request->getPost("pull_to_refresh"),
            "loader_color"    => esc($loader_color),
            "display_title"   => (int) $this->request->getPost("display_title"),
            "icon_color"      => esc($icon_color),
            "active_color"    => esc($active_color),
        ]);
        return $this->respond([
            "code" => 200
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for update template
     * @return array
     */
    private function update_detail_validation_type(): array
    {
        return [
            "color_theme"     => ["label" => lang("Fields.field_8"),  "rules" => "required|min_length[7]|max_length[7]"],
            "color_title"     => ["label" => lang("Fields.field_9"),  "rules" => "required|in_list[0,1]"],
            "template"        => ["label" => lang("Fields.field_10"), "rules" => "required|in_list[0,1,2,3]"],
            "loader"          => ["label" => lang("Fields.field_15"), "rules" => "required|in_list[0,1,2]"],
            "pull_to_refresh" => ["label" => lang("Fields.field_16"), "rules" => "required|in_list[0,1]"],
            "loader_color"    => ["label" => lang("Fields.field_18"), "rules" => "required|min_length[7]|max_length[7]"],
            "display_title"   => ["label" => lang("Fields.field_45"), "rules" => "required|in_list[0,1]"],
            "icon_color"      => ["label" => lang("Fields.field_125"), "rules" => "required|min_length[7]|max_length[7]"],
            "active_color"    => ["label" => lang("Fields.field_126"), "rules" => "required|min_length[7]|max_length[7]"],
        ];
    }

    /**
     * Get validation rules for create div style
     * @return array
     */
    private function create_div_validation_type(): array
    {
        return [
            "name" => ["label" => lang("Fields.field_46"),  "rules" => "required|min_length[2]|max_length[100]"],
        ];
    }
}
