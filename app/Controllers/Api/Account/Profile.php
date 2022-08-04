<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\UsersModel;
use App\Libraries\Authorization\Passport;
use ReflectionException;

class Profile extends PrivateController
{
    private $users;
    private $passport;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->users = new UsersModel();
        $this->passport = new Passport(12, false);
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Update profile
     * @return mixed
     * @throws ReflectionException
     */
    public function update()
    {
        if (!$this->validate($this->update_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $email = esc($this->request->getPost("email"));
        if ($email != $this->user["email"]) {
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
        $password = esc($this->request->getPost("password"));
        if ($password) {
            $user = $this->users
                ->where("id", $this->user['id'])
                ->select("password")
                ->first();
            if (!$this->passport->CheckPassword($password, $user["password"])) {
                return $this->respond([
                    "code"    => 400,
                    "message" => [
                        "error" => lang("Message.message_1")
                    ],
                ], 400);
            } else {
                $this->users->update($this->user["id"], [
                    "password" => $this->passport->HashPassword(
                        esc($this->request->getPost("new_password"))
                    ),
                ]);
            }
        }
        $this->users->update($this->user["id"], [
            "email" => $email
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for profile update
     * @return array
     */
    private function update_validation_type(): array
    {
        return [
            "email"         => ["label" => lang("Fields.field_1"),  "rules" => "required|valid_email|max_length[100]"],
            "password"      => ["label" => lang("Fields.field_2"),  "rules" => "permit_empty|max_length[100]|alpha_numeric"],
            "new_password"  => ["label" => lang("Fields.field_77"), "rules" => "permit_empty|required_with[password]|max_length[100]|alpha_numeric"],
        ];
    }
}