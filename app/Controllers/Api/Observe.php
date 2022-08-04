<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\Notification;
use App\Libraries\Settings;
use App\Models\AppsModel;
use App\Models\BuildsModel;
use App\Models\BuildsQueueModel;
use App\Models\UsersModel;
use App\Libraries\CodeMagic;
use ReflectionException;

class Observe extends BaseController
{
    private $apps;
    private $builds;
    private $queue;
    private $codemagic;
    private $notification;
    private $settings;
    private $users;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->builds = new BuildsModel();
        $this->queue = new BuildsQueueModel();
        $this->codemagic = new CodeMagic();
        $this->notification = new Notification();
        $this->settings = new Settings();
        $this->users = new UsersModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get notification about finished build
     * @param string $uid
     * @return void
     * @throws ReflectionException
     */
    public function notice(string $uid = "")
    {
        $app = $this->apps
            ->where("uid", esc($uid))
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
        $build = $this->builds
            ->where(["app_id" => $app["id"], "status" => 0])
            ->select("id")
            ->first();
        if (!$build) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_46")
                ],
            ], 400);
        }
        $queue_id = $this->queue->insert([
            "build" => $build["id"]
        ]);
        return $this->respond([
            "code"     => 200,
            "queue_id" => $queue_id
        ], 200);
    }

    /**
     * Get status builds
     * @return void
     * @throws ReflectionException
     */
    public function cron()
    {
        $queue = $this->queue
            ->where("status", 0)
            ->select("id,build")
            ->findAll();
        foreach ($queue as $item) {
            $build = $this->builds
                ->where(["id" => $item["build"]])
                ->select("id,build_id,app_id")
                ->first();
            $app = $this->apps
                ->where(["id" => $build["app_id"]])
                ->select("id,balance,uid,name,user")
                ->first();
            $user = $this->users
                ->where("id", $app["user"])
                ->select("email")
                ->first();
            $cm_build = $this->codemagic->status($build["build_id"]);
            if (!$cm_build["event"]) {
                $this->queue->update($item["id"], [
                    "status" => 1
                ]);
                $this->builds->update($build["id"], [
                    "status"  => 1,
                    "fail"    => 1,
                    "message" => lang("Message.message_47")
                ]);
            } else {
                $this->result_handler($cm_build, $item, $build, $app);
            }
            $this->send_notify_email($user["email"], $cm_build["build"]["status"], $app);
        }
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Build result handler
     * @param array $cm_build
     * @param array $item
     * @param array $build
     * @param array $app
     * @return void
     * @throws ReflectionException
     */
    public function result_handler(array $cm_build, array $item, array $build, array $app)
    {
        $status = $cm_build["build"]["status"];
        switch($status)
        {
            case "finished" :
                $this->queue->update($item["id"], [
                    "status" => 1,
                ]);
                $this->builds->update($build["id"], [
                    "status" => 1,
                    "static" => $cm_build["build"]["artefacts"][0]["url"]
                ]);
                $this->apps->update($app["id"], [
                    "balance" => $app["balance"] - 1
                ]);
                break;
            case "failed" :
                $this->queue->update($item["id"], [
                    "status" => 1,
                ]);
                $this->builds->update($build["id"], [
                    "status"  => 1,
                    "fail"    => 1,
                    "message" => $cm_build["build"]["message"]
                ]);
                break;
            default :
                break;
        }
    }

    /**
     * Send email about change build status
     * @param string $email
     * @param string $status
     * @param array $app
     * @return void
     */
    private function send_notify_email(string $email, string $status, array $app)
    {
        $emailVariables = [
            "{SITE_URL}",
            "{SITE_NAME}",
            "{SITE_LOGO}",
            "{LINK}",
            "{APP}",
            "{STATUS}"
        ];
        $codeVariable = [
            $this->settings->get_config("site_url"),
            $this->settings->get_config("site_name"),
            base_url("static/".$this->settings->get_config("site_logo")),
            $this->settings->get_config("site_url")."account/apps/".$app["uid"]."/build",
            $app["name"],
            $status == "finished" ? lang("Fields.field_122") : lang("Fields.field_123")
        ];
        $str = file_get_contents(WRITEPATH."emails/build.html");
        $content = str_replace($emailVariables, $codeVariable, $str);
        $subject = lang("Fields.field_124");
        $this->notification->send($email, $subject, $content);
    }
}