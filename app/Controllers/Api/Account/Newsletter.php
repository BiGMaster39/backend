<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\AppsModel;
use App\Models\PushModel;
use App\Libraries\OneSignal;
use App\Libraries\Common;
use CodeIgniter\Config\Services;

define("LIMIT", 20);

class Newsletter extends PrivateController
{
    private $apps;
    private $push;
    private $oneSignal;
    private $common;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->push = new PushModel();
        $this->oneSignal = new OneSignal();
        $this->common = new Common();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list apps with connected OneSignal
     * @return mixed
     */
    public function apps()
    {
        $apps = $this->apps
            ->where(["user" => $this->user["id"], "status" => 1])
            ->select("id,name,link,uid")
            ->findAll();
        $list = [];
        foreach ($apps as $app) {
            $push = $this->push
                ->where(["app_id" => $app["id"], "android_enabled" => 1])
                ->countAllResults();
            if ($push) {
                $list[] = [
                    "uid"  => $app["uid"],
                    "name" => $app["name"],
                    "link" => $app["link"],
                    "icon" => $this->get_icon($app["uid"])
                ];
            }
        }
        return $this->respond(["list" => $list], 200);
    }

    /**
     * Newsletter history list
     * @param string $uid
     * @param int $offset
     * @return mixed
     */
    public function list(string $uid = "", int $offset = 0)
    {
        $encrypter = Services::encrypter();
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"], "status" => 1])
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
        $push = $this->push
            ->where(["app_id" => $app["id"], "android_enabled" => 1])
            ->first();
        if (!$push) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_52")
                ],
            ], 400);
        }
        $app_id = $encrypter->decrypt($push["os_app_id"]);
        $app_key = $encrypter->decrypt($push["os_api_key"]);
        $history = $this->oneSignal->notifications($app_id, $app_key, $offset);
        if (!$history["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => lang("Message.message_53")
                ],
            ], 502);
        }
        $list = [];
        foreach ($history["notifications"] as $notification) {
            if (empty($notification['platform_delivery_stats']['android'])) {
                $stat = null;
            } else {
                $stat = $notification['platform_delivery_stats']['android'];
            }
            $list[] = [
                'big_picture'             => $notification['big_picture'],
                'contents'                => $notification['contents']['en'],
                'headings'                => $notification['headings']['en'],
                'platform_delivery_stats' => $stat,
                'created'                 => date('d-m-Y H:i', $notification['queued_at']),
                'android_led_color'       => $notification['android_led_color'],
                'converted'               => $notification['converted']
            ];
        }
        $project = $this->oneSignal->app($app_id, $app_key);
        if (!$project["event"]) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => lang("Message.message_57")
                ],
            ], 502);
        }
        return $this->respond([
            "list"  => $list,
            "count" => (int) $history["count"],
            "stat"  => [
                "total"  => $project["detail"]["players"],
                "active" => $project["detail"]["messageable_players"]
            ],
        ], 200);
    }

    /**
     * Create new PUSH newsletter
     * @param string $uid
     * @return mixed
     */
    public function create(string $uid = "")
    {
        $encrypter = Services::encrypter();
        if (!$this->validate($this->create_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"], "status" => 1])
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
        $push = $this->push
            ->where(["app_id" => $app["id"], "android_enabled" => 1])
            ->first();
        if (!$push) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_52")
                ],
            ], 400);
        }
        $led = esc($this->request->getPost("led"));
        if (!$this->common->hex_validation($led)) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_56")
                ],
            ], 400);
        }
        $image = $this->request->getFile('image');
        $icon = $this->get_icon($app["uid"]);
        $body = [
            'app_id'            => $encrypter->decrypt($push["os_app_id"]),
            'included_segments' => ['All'],
            'contents'          => [
                'en' => esc($this->request->getPost('message'))
            ],
            'headings'          => [
                'en' => esc($this->request->getPost('title'))
            ],
            'large_icon'        => !$icon ? base_url('upload/default/icons/android/hdpi_72.png') : $icon,
            'android_led_color' => $led,
            'big_picture' 		=> $this->get_image($image)
        ];
        $result = $this->oneSignal->send($body, $encrypter->decrypt($push["os_api_key"]));
        $code = !$result["event"] ? 502 : 200;
        return $this->respond([
            "code"    => $code,
            "result"  => $result,
            "image"   => $image
        ], $code);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get newsletter image
     * @param string $image
     * @return string
     */
    private function get_image(string $image): string
    {
        if (!$image) {
            return "";
        }
        $ext = $image->getExtension();
        if ($ext !== "png" && $ext !== "jpg" && $ext !== "jpeg") {
            return "";
        }
        $nameImage = $image->getRandomName();
        echo("name ".$nameImage);
        $image->move(ROOTPATH.'upload/newsletter', $nameImage);
        return base_url('upload/newsletter/'.$nameImage);
    }

    /**
     * Get validation rules for create push
     * @return array
     */
    private function create_validation_type(): array
    {
        return [
            'title'    => ['label' => lang("Fields.field_78"), 'rules' => 'required|min_length[3]|max_length[100]'],
            'message'  => ['label' => lang("Fields.field_79"), 'rules' => 'required|max_length[300]|min_length[3]'],
            'led'      => ['label' => lang("Fields.field_81"), 'rules' => 'required|max_length[7]|min_length[7]']
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