<?php
namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Libraries\Authorization\Passport;
use App\Libraries\Notification;
use App\Libraries\Settings;
use App\Models\UsersModel;
use ReflectionException;

define("LIMIT", 20);

class Users extends PrivateController
{
    private $users;
    private $passport;
    private $notification;
    private $settings;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->users = new UsersModel();
        $this->passport = new Passport(12, false);
        $this->notification = new Notification();
        $this->settings = new Settings();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list all users
     * @param int $offset
     * @return mixed
     */
    public function list(int $offset = 0)
    {
        $search = esc($this->request->getGet("search"));
        $users = $this->users
            ->like("email", $search)
            ->orderBy("id", "DESC")
            ->findAll(LIMIT, $offset);
        $count = $this->users
            ->like("email", $search)
            ->orderBy("id", "DESC")
            ->countAll();
        return $this->respond([
            "code"   => 200,
            "list"   => $this->build_results($users),
            "count"  => $count
        ], 200);
    }

    /**
     * Get user detail
     * @param int $id
     * @return mixed
     */
    public function detail(int $id = 0)
    {
        $user = $this->users
            ->where("id", $id)
            ->first();
        if (!$user) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => lang("Message.message_60")
                ],
            ], 400);
        }
        return $this->respond([
            "code"   => 200,
            "email"  => $user["email"],
            "admin"  => (int) $user["admin"]
        ], 200);
    }

    /**
     * Update user detail
     * @param int $id
     * @return mixed
     * @throws ReflectionException
     */
    public function update(int $id = 0)
    {
        $user = $this->users
            ->where("id", (int) $id)
            ->first();
        if (!$user) {
            return $this->respond([
                "code"    => 502,
                "message" => [
                    "error" => lang("Message.message_60")
                ],
            ], 400);
        }
        if (!$this->validate($this->update_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $email = esc($this->request->getPost("email"));
        if ($email != $user["email"]) {
            $double = $this->users
                ->where("email", $email)
                ->countAllResults();
            if ($double) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_2")
                    ],
                ], 400);
            }
        }
        $password = esc($this->request->getPost("new_password"));
        if ($password) {
            $this->users->update($user["id"], [
                "password" => $this->passport->HashPassword($password),
            ]);
            $this->send_pass_email($password, $user['email']);
        }
        $this->users->update($user["id"], [
            "email" => $email,
            "admin" => (int) $this->request->getPost("admin")
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Send new password
     * @param string $password
     * @param string $email
     * @return void
     */
    private function send_pass_email(string $password, string $email)
    {
        $emailVariables = [
            '{EMAIL}',
            '{PASSWORD}',
            "{SITE_URL}",
            "{SITE_NAME}",
            "{SITE_LOGO}"
        ];
        $codeVariable = [
            $email,
            $password,
            $this->settings->get_config("site_url"),
            $this->settings->get_config("site_name"),
            base_url("static/".$this->settings->get_config("site_logo"))
        ];
        $str = file_get_contents(WRITEPATH."emails/password.html");
        $content = str_replace($emailVariables, $codeVariable, $str);
        $subject = lang("Fields.field_5");
        $this->notification->send($email, $subject, $content);
    }

    /**
     * Get validation rules for profile update
     * @return array
     */
    private function update_validation_type(): array
    {
        return [
            "email"         => ["label" => lang("Fields.field_1"),  "rules" => "required|valid_email|max_length[100]"],
            "new_password"  => ["label" => lang("Fields.field_77"), "rules" => "permit_empty|required_with[password]|max_length[100]|alpha_numeric"],
            "admin"         => ["label" => lang("Fields.field_107"),  "rules" => "required|in_list[0,1]"],
        ];
    }

    /**
     * Build list result for display
     * @param array $list
     * @return array
     */
    private function build_results(array $list): array
    {
        $users = [];
        foreach ($list as $item) {
            $users[] = [
                "id"      => (int) $item["id"],
                "email"   => $item["email"],
                "status"  => (int) $item["status"],
                "created" => date('d-m-Y H:i', $item['created_at']),
                "admin"   => (int) $item["admin"],
                "deleted" => (bool) $item["deleted_at"]
            ];
        }
        return $users;
    }
}