<?php namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\PushModel;
use App\Libraries\OneSignal;
use App\Libraries\Uid;
use CodeIgniter\Config\Services;
use ReflectionException;

class Push extends PrivateController
{
    private $apps;
    private $push;
    private $oneSignal;
    private $uid;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->push = new PushModel();
        $this->oneSignal = new OneSignal();
        $this->uid = new Uid();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get push settings detail
     * @param string $uid
     * @return mixed
     */
    public function detail(string $uid = "")
    {
        $encrypter = Services::encrypter();
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
        $detail = $this->push
            ->where("app_id", $app["id"])
            ->first();
        return $this->respond([
            "code"   => 200,
            "detail" => [
                "ios"      => (int) $detail["apple_enabled"],
                "android"  => (int) $detail["android_enabled"],
                "app_id"   => !$detail['os_app_id'] ? "" : $encrypter->decrypt($detail['os_app_id']),
                "api_key"  => !$detail['os_api_key'] ? "" : $encrypter->decrypt($detail['os_api_key']),
                "sign_key" => $detail["sign_key"]
            ],
        ], 200);
    }

    /**
     * Reissue Key for verifying the player ID signature
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function reissue_sign(string $uid = "")
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
        $detail = $this->push
            ->where("app_id", $app["id"])
            ->first();
        $sign = hash('sha256', $this->uid->create());
        $this->push->update($detail["id"], [
            "sign_key" => $sign
        ]);
        return $this->respond([
            "code"     => 200,
            "sign_key" => $sign
        ], 200);
    }

    /**
     * Update push settings
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function update(string $uid = "")
    {
        $encrypter = Services::encrypter();
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
        if (!$this->validate($this->update_push_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $detail = $this->push
            ->where("app_id", $app["id"])
            ->select("id")
            ->first();
        $android_enabled = (int) $this->request->getPost("android");
        $app_id = esc($this->request->getPost("app_id"));
        $api_key = esc($this->request->getPost("api_key"));
        if ($android_enabled) {
            // test OneSignal connect
            $test = $this->oneSignal->app($app_id, $api_key);
            if (!$test["event"]) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_20")
                    ],
                ], 400);
            }
        }
        $this->push->update($detail["id"], [
            "android_enabled" => $android_enabled,
            "os_app_id"       => $encrypter->encrypt($app_id),
            "os_api_key"      => $encrypter->encrypt($api_key)
        ]);
        return $this->respond([
            "code"   => 200,
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for update push settings
     * @return array
     */
    private function update_push_validation_type(): array
    {
        return [
            "android" => ["label" => lang("Fields.field_32"),  "rules" => "required|in_list[0,1]"],
            "app_id"  => ["label" => lang("Fields.field_33"),  "rules" => "max_length[100]"],
            "api_key" => ["label" => lang("Fields.field_34"),  "rules" => "max_length[100]"],
        ];
    }
}