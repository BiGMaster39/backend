<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\DrawersModel;
use App\Libraries\Common;
use App\Libraries\GitHub;
use ReflectionException;

class Drawer extends PrivateController
{
    private $apps;
    private $drawers;
    private $common;
    private $github;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->drawers = new DrawersModel();
        $this->common = new Common();
        $this->github = new GitHub();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get drawer header settings detail
     * @param string $uid
     * @return mixed
     */
    public function detail(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
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
        $header = $this->drawers
            ->where("app_id", $app["id"])
            ->first();
        return $this->respond([
            "code"   => 200,
            "detail" => [
                "mode"         => (int) $header["mode"],
                "color"        => $header["color"],
                "theme"        => (int) $header["theme"],
                "logo_enabled" => (int) $header["logo_enabled"],
                "title"        => $header["title"],
                "subtitle"     => $header["subtitle"],
                "background"   => !$header["background"]
                    ? null
                    : base_url('upload/drawer/'.$app['uid'].'/'.$header["background"]),
                "logo"         => !$header["logo"]
                    ? null
                    : base_url('upload/drawer/'.$app['uid'].'/'.$header["logo"])
            ]
        ], 200);
    }

    /**
     * Update drawer settings
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function update_drawer(string $uid = "")
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
        $header = $this->drawers
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_detail_validation_type())) {
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
        $this->drawers->update($header["id"], [
            "mode"         => (int) $this->request->getPost("mode"),
            "color"        => esc($color),
            "theme"        => (int) $this->request->getPost("theme"),
            "logo_enabled" => (int) $this->request->getPost("logo_enabled"),
            "title"        => esc($this->request->getPost("title")),
            "subtitle"     => esc($this->request->getPost("subtitle")),
        ]);
        return $this->respond([
            "code"   => 200,
        ], 200);
    }

    /**
     * Upload background image
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_background(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
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
        $header = $this->drawers
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_background_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        if ( !is_dir( ROOTPATH.'upload/drawer/'.$app['uid'] ) ) {
            mkdir(ROOTPATH.'upload/drawer/'.$app['uid'], 0777, true);
        }
        $image = $this->request->getFile('background');
        $name = $image->getRandomName();
        $image->move(ROOTPATH.'upload/drawer/'.$app['uid'], $name);
        $git_result = $this->github->create_commit(
            $app["uid"],
            "assets/app/drawer_background.png",
            file_get_contents(ROOTPATH.'upload/drawer/'.$app['uid'].'/'.$name)
        );
        if (!$git_result["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $git_result["message"]["error"]
                ],
            ], 502);
        }
        $this->drawers->update($header["id"], [
            "background" => $name
        ]);
        return $this->respond([
            "code" => 200,
            "uri"  => base_url('upload/drawer/'.$app['uid'].'/'.$name)
        ], 200);
    }

    /**
     * Upload logo image
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_logo(string $uid = "")
    {
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
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
        $header = $this->drawers
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_logo_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        if ( !is_dir( ROOTPATH.'upload/drawer/'.$app['uid'] ) ) {
            mkdir(ROOTPATH.'upload/drawer/'.$app['uid'], 0777, true);
        }
        $image = $this->request->getFile('logo');
        $name = $image->getRandomName();
        $image->move(ROOTPATH.'upload/drawer/'.$app['uid'], $name);
        $git_result = $this->github->create_commit(
            $app["uid"],
            "assets/app/logo.png",
            file_get_contents(ROOTPATH.'upload/drawer/'.$app['uid'].'/'.$name)
        );
        if (!$git_result["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $git_result["message"]["error"]
                ],
            ], 502);
        }
        $this->drawers->update($header["id"], [
            "logo" => $name
        ]);
        return $this->respond([
            "code" => 200,
            "uri"  => base_url('upload/drawer/'.$app['uid'].'/'.$name)
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for update drawer settings
     * @return array
     */
    private function update_detail_validation_type(): array
    {
        return [
            "mode"         => ["label" => lang("Fields.field_47"), "rules" => "required|in_list[0,1,2]"],
            "color"        => ["label" => lang("Fields.field_24"), "rules" => "required|min_length[7]|max_length[7]"],
            "theme"        => ["label" => lang("Fields.field_27"), "rules" => "required|in_list[0,1]"],
            "logo_enabled" => ["label" => lang("Fields.field_28"), "rules" => "required|in_list[0,1]"],
            "title"        => ["label" => lang("Fields.field_48"), "rules" => "max_length[30]"],
            "subtitle"     => ["label" => lang("Fields.field_49"), "rules" => "max_length[30]"],
        ];
    }

    /**
     * Get validation rules for upload background
     * @return array
     */
    private function update_background_validation_type(): array
    {
        return [
            'background' => ['label' => lang("Fields.field_29"), 'rules' => 'uploaded[background]|max_size[background,500]|ext_in[background,png,jpg]|max_dims[background,1920,1920]'],
        ];
    }

    /**
     * Get validation rules for upload logo
     * @return array
     */
    private function update_logo_validation_type(): array
    {
        return [
            'logo' => ['label' => lang("Fields.field_30"), 'rules' => 'uploaded[logo]|max_size[logo,500]|ext_in[logo,png,jpg]|max_dims[logo,1200,1200]'],
        ];
    }
}