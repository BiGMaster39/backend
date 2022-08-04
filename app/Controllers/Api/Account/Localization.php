<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\LocalsModel;
use App\Libraries\GitHub;
use ReflectionException;

class Localization extends PrivateController
{
    private $apps;
    private $locals;
    private $github;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->locals = new LocalsModel();
        $this->github = new GitHub();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get locals detail
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
        $detail = $this->locals
            ->where("app_id", $app["id"])
            ->first();
        $locals = [];
        $locals[] = ["name" => $detail["string_1"], "loading" => false];
        $locals[] = ["name" => $detail["string_2"], "loading" => false];
        $locals[] = ["name" => $detail["string_3"], "loading" => false];
        $locals[] = ["name" => $detail["string_4"], "loading" => false];
        $locals[] = ["name" => $detail["string_5"], "loading" => false];
        $locals[] = ["name" => $detail["string_6"], "loading" => false];
        $locals[] = ["name" => $detail["string_7"], "loading" => false];
        $locals[] = ["name" => $detail["string_8"], "loading" => false];
        return $this->respond([
            "code"   => 200,
            "locals" => $locals,
            "images" => [
                "offline" => !$detail["offline_image"] ? null : base_url('upload/info/'.$app['uid'].'/'.$detail["offline_image"]),
                "error"   => !$detail["error_image"] ? null : base_url('upload/info/'.$app['uid'].'/'.$detail["error_image"])
            ]
        ], 200);
    }

    /**
     * Update text value
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function update_text(string $uid = "")
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
        $detail = $this->locals
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_string_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $key = (int) $this->request->getPost("id");
        $this->locals->update($detail["id"], [
            'string_'.$key => esc($this->request->getPost("name"))
        ]);
        return $this->respond([
            "code"   => 200,
        ], 200);
    }

    /**
     * Refresh text value
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function refresh_text(string $uid = "")
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
        $detail = $this->locals
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->refresh_string_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $key = (int) $this->request->getPost("id");
        if ($key === 1) {
            $value = lang("Fields.field_53");
        } else if ($key === 2) {
            $value = lang("Fields.field_54");
        } else if ($key === 3) {
            $value = lang("Fields.field_55");
        } else if ($key === 4) {
            $value = lang("Fields.field_56");
        } else if ($key === 5) {
            $value = lang("Fields.field_57");
        } else if ($key === 6) {
            $value = lang("Fields.field_58");
        } else if ($key === 7) {
            $value = lang("Fields.field_59");
        } else {
            $value = lang("Fields.field_60");
        }
        $this->locals->update($detail["id"], [
            'string_'.$key => $value
        ]);
        return $this->respond([
            "code"   => 200,
            "value"  => $value
        ], 200);
    }

    /**
     * Refresh text value
     * @param string $uid
     * @param string $type
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_image(string $uid = "", string $type = "offline")
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
        $detail = $this->locals
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        if (!$this->validate($this->update_image_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        if ( !is_dir( ROOTPATH.'upload/info/'.$app['uid'] ) ) {
            mkdir(ROOTPATH.'upload/info/'.$app['uid'], 0777, true);
        }
        $image = $this->request->getFile('image');
        $name = $image->getRandomName();
        $image->move(ROOTPATH.'upload/info/'.$app['uid'], $name);
        $git_result = $this->github->create_commit(
            $app["uid"],
            $type === "offline" ? "assets/app/wifi.png" : "assets/app/error.png",
            file_get_contents(ROOTPATH.'upload/info/'.$app['uid'].'/'.$name)
        );
        if (!$git_result["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $git_result["message"]["error"]
                ],
            ], 502);
        }
        if ($type === "offline") {
            $this->locals->update($detail["id"], [
                "offline_image" => $name
            ]);
        } else {
            $this->locals->update($detail["id"], [
                "error_image" => $name
            ]);
        }
        return $this->respond([
            "code" => 200,
            "uri"  => base_url('upload/info/'.$app['uid'].'/'.$name)
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for update string value
     * @return array
     */
    private function update_string_validation_type(): array
    {
        return [
            "name" => ["label" => lang("Fields.field_51"),  "rules" => "required|min_length[2]|max_length[500]"],
            "id"   => ["label" => lang("Fields.field_52"),  "rules" => "required|numeric|is_natural_no_zero|less_than_equal_to[8]"],
        ];
    }

    /**
     * Get validation rules for refresh string value
     * @return array
     */
    private function refresh_string_validation_type(): array
    {
        return [
            "id"   => ["label" => lang("Fields.field_52"),  "rules" => "required|numeric|is_natural_no_zero|less_than_equal_to[8]"],
        ];
    }

    /**
     * Get validation rules for upload image
     * @return array
     */
    private function update_image_validation_type(): array
    {
        return [
            'image' => ['label' => lang("Fields.field_30"), 'rules' => 'uploaded[image]|max_size[image,500]|ext_in[image,png]|max_dims[image,1200,1200]'],
        ];
    }
}