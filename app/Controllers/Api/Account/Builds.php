<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\BuildsModel;
use App\Models\AppsModel;
use App\Models\NavigationModel;
use App\Models\SignsAndroidModel;
use App\Models\SignsIosModel;
use App\Libraries\Uid;
use App\Libraries\Flutter\Builder;
use App\Libraries\Common;
use App\Libraries\CodeMagic;
use ReflectionException;

define("LIMIT", 20);

class Builds extends PrivateController
{
    private $builds;
    private $apps;
    private $navigation;
    private $android_signs;
    private $ios_signs;
    private $uid;
    private $builder;
    private $common;
    private $codemagic;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->builds = new BuildsModel();
        $this->apps = new AppsModel();
        $this->navigation = new NavigationModel();
        $this->android_signs = new SignsAndroidModel();
        $this->ios_signs = new SignsIosModel();
        $this->uid = new Uid();
        $this->builder = new Builder();
        $this->common = new Common();
        $this->codemagic = new CodeMagic();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list all builds
     * @param string $uid
     * @param int $order
     * 0 - all apps
     * 1 - android
     * 2 - ios
     * @param int $offset
     * @return mixed
     */
    public function list(string $uid = "", int $order = 0, int $offset = 0)
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
        if ($order == 0) {
            $where = ["app_id" => $app["id"]];
        } elseif ($order == 1) {
            $where = ["app_id" => $app["id"], "platform" => "android"];
        } else {
            $where = ["app_id" => $app["id"], "platform" => "ios"];
        }
        $builds = $this->builds
            ->where($where)
            ->orderBy("id", "DESC")
            ->findAll(LIMIT, (int) $offset);
        $count = $this->builds
            ->where($where)
            ->countAllResults();
        $list = [];
        $current_version = "1.0.0";
        foreach ($builds as $key => $build) {
            $list[] = [
                "uid"      => $build["uid"],
                "platform" => $build["platform"],
                "status"   => (int) $build["status"],
                "version"  => $build["version"],
                "publish"  => (int) $build["publish"],
                "created"  => date('d-m-Y H:i', $build['created_at']),
                "format"   => $build["platform"] == "ios" ? "ipa" : $build["format"],
                "fail"     => (bool) $build["fail"],
                "message"  => $build["message"],
                "static"   => $build["static"],
            ];
            $index = $key + 1;
            if ($index == $count) {
                $current_version = $this->get_next_version($build["version"]);
            }
        }
        return $this->respond([
            "code"         => 200,
            "list"         => $list,
            "count"        => (int) $count,
            "next_version" => $current_version
        ], 200);
    }

    /**
     * Create new build
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function create(string $uid = "")
    {
        if (!$this->validate($this->create_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
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
        $isValid = $this->starter_validation($app);
        if ($isValid["code"] == 400) {
            return $this->respond($isValid, 400);
        }
        $version = esc($this->request->getPost("version"));
        if (!$this->common->version_format_validation($version)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_39")
                ],
            ], 400);
        }
        $platform = esc($this->request->getPost("platform"));
        $android_key = esc($this->request->getPost("android_key_id"));
        $ios_key = esc($this->request->getPost("ios_key_id"));
        $sign_uid = $platform == "android" ? $android_key : $ios_key;
        $signing_id = $this->sign_validation($sign_uid, $platform);
        if (!$signing_id) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_40")
                ],
            ], 400);
        }
        $format = esc($this->request->getPost("format"));
        $publish = (int) $this->request->getPost("publish");
        if ($platform == "android") {
            $target = $this->builder->generate_android_project($app, $version, $format, $signing_id);
        } else {
            // change ios
            $target = $this->builder->generate_ios_project($app, $version, $signing_id, $publish);
        }
        if (!$target["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $target["message"]["error"]
                ],
            ], 502);
        }
        $uid = $this->uid->create();
        $cm_result = $this->codemagic->build(
            $platform == "android" ? "android-workflow" : "ios-workflow",
            $app["uid"]
        );
        if (!$cm_result["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => $cm_result["message"]["error"]
                ],
            ], 502);
        }
        $this->builds->insert([
            "app_id"         => $app["id"],
            "uid"            => $uid,
            "platform"       => $platform,
            "status"         => 0,
            "version"        => $version,
            "android_key_id" => $platform == "android" ? $signing_id : 0,
            "ios_key_id"     => $platform == "ios" ? $signing_id : 0,
            "publish"        => $publish,
            "format"         => $format,
            "build_id"       => $cm_result["id"]
        ]);
        return $this->respond([
            "code"   => 200,
            "detail" => [
                "uid"      => $uid,
                "platform" => $platform,
                "status"   => 0,
                "version"  => $version,
                "publish"  => $publish,
                "created"  => date('d-m-Y H:i'),
                "format"   => $platform == "ios" ? "ipa" : $format,
                "fail"     => false
            ]
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get number next version
     * @param string $version
     * @return string
     */
    private function get_next_version(string $version): string
    {
        $parse = explode('.', $version);
        $new_version = 0;
        if (is_numeric($parse["2"])) {
            $new_version = $parse["2"] + 1;
        }
        return '1.0.'.$new_version;
    }

    /**
     * Start build validation
     * @param array $app
     * @return array
     */
    private function starter_validation(array $app): array
    {
        if ($app["balance"] == 0) {
            return [
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_30")
                ],
            ];
        }
        $isActiveBuilds = $this->builds
            ->where(["app_id" => $app["id"], "status" => 0])
            ->countAllResults();
        if ($isActiveBuilds) {
            return [
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_31")
                ],
            ];
        }
        if (!$this->check_navigation($app["id"], $app["template"])) {
            return [
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_34")
                ],
            ];
        }
        return ["code" => 200];
    }

    /**
     * Check app signing
     * @param string $uid
     * @param string $platform
     * @return int|null
     */
    private function sign_validation(string $uid, string $platform): ?int
    {
        if ($platform == "android") {
            $sign = $this->android_signs
                ->where(["user_id" => $this->user["id"], "uid" => $uid])
                ->select("id")
                ->first();
            if ($sign) {
                return $sign["id"];
            } else {
                return null;
            }
        } else {
            $sign = $this->ios_signs
                ->where(["user_id" => $this->user["id"], "uid" => $uid])
                ->select("id")
                ->first();
            if ($sign) {
                return $sign["id"];
            } else {
                return null;
            }
        }
    }

    /**
     * Check navigation items
     * @param int $id
     * @param int $template
     * @return bool
     */
    private function check_navigation(int $id, int $template): bool
    {
        if ($template < 2) {
            $count = $this->navigation
                ->where(["app_id" => $id, "type" => 0])
                ->countAllResults();
            if (!$count) {
                return false;
            } else {
                return true;
            }
        } else {
            return true;
        }
    }

    /**
     * Get validation rules for create new build
     * @return array
     */
    private function create_validation_type(): array
    {
        return [
            "version"         => ["label" => lang("Fields.field_69"), "rules" => "required|min_length[3]|max_length[8]"],
            "platform"        => ["label" => lang("Fields.field_70"), "rules" => "required|in_list[android,ios]"],
            "format"          => ["label" => lang("Fields.field_74"), "rules" => "required|in_list[apk,aab]"],
            "android_key_id"  => ["label" => lang("Fields.field_71"), "rules" => "max_length[100]"],
            "ios_key_id"      => ["label" => lang("Fields.field_72"), "rules" => "max_length[100]"],
            "publish"         => ["label" => lang("Fields.field_75"), "rules" => "required|in_list[0,1]"],
        ];
    }
}