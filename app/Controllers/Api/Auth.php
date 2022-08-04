<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\UsersModel;
use App\Models\ResetAttemptsModel;
use App\Libraries\Authorization\Passport;
use App\Libraries\Authorization\JWT;
use App\Libraries\Uid;
use App\Libraries\Notification;
use App\Libraries\Settings;
use App\Libraries\Authorization\SignatureInvalidException;
use Config\Services;
use UnexpectedValueException;
use ReflectionException;

class Auth extends BaseController
{
    private $users;
    private $reset;
    private $passport;
    private $uid;
    private $notification;
    private $settings;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->users = new UsersModel();
        $this->reset = new ResetAttemptsModel();
        $this->passport = new Passport(12, false);
        $this->uid = new Uid();
        $this->notification = new Notification();
        $this->settings = new Settings();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Start sign in to account by email/password
     * @return mixed
     */
    public function sign_in()
    {
        if (!$this->validate($this->login_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $user = $this->users
            ->where([
                "email"      => esc($this->request->getPost("email")),
                "deleted_at" => 0
            ])
            ->select("id,email,password,admin")
            ->first();
        if (!$user) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_1")
                ],
            ], 400);
        }
        $password =  esc($this->request->getPost("password"));
        if ($this->passport->CheckPassword($password, $user["password"])) {
            return $this->respond([
                "code"    => 200,
                "user"    => [
                    "email" => $user['email'],
                    "token" => $this->create_auth_tokens($user["id"]),
                    "login" => true,
                    "admin" => (bool) $user["admin"]
                ],
            ], 200);
        } else {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_1")
                ],
            ], 400);
        }
    }

    /**
     * Start sign in with Google
     * @return mixed
     * @throws ReflectionException
     */
    public function google_in()
    {
        helper('text');
        if (!$this->validate($this->google_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $client = Services::curlrequest();
        $response = $client->request('GET', 'https://oauth2.googleapis.com/tokeninfo', [
            'query' => [
                'id_token' => esc($this->request->getPost("id_token"))
            ],
        ]);
        $body = json_decode($response->getBody());
        if (time() > $body->exp) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_58")
                ],
            ], 400);
        }
        $user = $this->users
            ->where(["email" => $body->email])
            ->select("id,email,password,admin,deleted_at")
            ->first();
        if (!$user) {
            // create new account
            $userId = $this->users->insert([
                "email"    => $body->email,
                "password" => $this->passport->HashPassword(random_string('alnum', 16)),
            ]);
            return $this->respond([
                "code"    => 200,
                "user"    => [
                    "email" => $body->email,
                    "token" => $this->create_auth_tokens($userId),
                    "login" => true
                ],
            ], 200);
        }
        if ($user['deleted_at']) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_1")
                ],
            ], 400);
        }
        // login
        return $this->respond([
            "code"    => 200,
            "user"    => [
                "email" => $user['email'],
                "token" => $this->create_auth_tokens($user["id"]),
                "login" => true,
                "admin" => (bool) $user["admin"]
            ],
        ], 200);
    }

    /**
     * Create account
     * @return mixed
     * @throws ReflectionException
     */
    public function sign_up()
    {
        if (!$this->validate($this->register_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $email = esc($this->request->getPost("email"));
        if ($this->users->where("email", $email)->first()) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_2")
                ],
            ], 400);
        }
        $userId = $this->users->insert([
            "email"    => $email,
            "password" => $this->passport->HashPassword(
                esc($this->request->getPost("password"))
            ),
        ]);
        return $this->respond([
            "code"    => 200,
            "user"    => [
                "email" => $email,
                "token" => $this->create_auth_tokens($userId),
                "login" => true
            ],
        ], 200);
    }

    /**
     * Start password reset
     * @return mixed
     * @throws ReflectionException
     */
    public function reset()
    {
        if (!$this->validate($this->reset_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $email = esc($this->request->getPost("email"));
        $user = $this->users
            ->where(["email" => $email, "status" => 0])
            ->select("id,email,status")
            ->first();
        if (!$user) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_3")
                ],
            ], 400);
        }
        $token = $this->uid->create();
        $this->reset->insert([
            "email" => $email,
            "token" => $token
        ]);
        $this->send_reset_email($token, $user['email']);
        return $this->respond([
            "code"    => 200,
            "message" => [
                "success" => lang("Message.message_4")
            ]
        ], 200);
    }

    /**
     * Get new password
     * @param string $token
     * @return mixed
     * @throws ReflectionException
     */
    public function password(string $token = "")
    {
        helper('text');
        if (!$token) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_5")
                ],
            ], 400);
        }
        $item = $this->reset
            ->where(['token' => esc($token), 'status' => 0])
            ->first();
        if (!$item) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_6")
                ],
            ], 400);
        }
        $current = date('Y-m-d', strtotime("-1 days"));
        if (strtotime($current) > $item['created_at']) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_7")
                ],
            ], 400);
        }
        $user = $this->users
            ->where('email', $item['email'])
            ->select('id,email')
            ->first();
        if (!$user) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_3")
                ],
            ], 400);
        }
        $new_pass = random_string('alnum', 16);
        $this->users->update($user['id'], [
            "password" => $this->passport->HashPassword($new_pass)
        ]);
        $this->reset->update($item['id'], [
            "status" => 1
        ]);
        $this->send_pass_email($new_pass, $user['email']);
        return $this->respond([
            "code"    => 200,
            "message" => [
                "success" => lang("Message.message_8")
            ]
        ], 200);
    }

    /**
     * Refresh JWT tokens
     * @return mixed
     */
    public function refresh()
    {
        if (empty($this->request->getHeaderLine('Refresh'))) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_9")
                ],
            ], 400);
        }
        $sign = $this->decodeJWT(esc($this->request->getHeaderLine('Refresh')));
        if (!$sign) {
            return $this->respond([
                "code"    => 401,
                "message" => [
                    "error" => lang("Message.message_10")
                ],
            ], 401);
        }
        if ($sign->type != 'refresh') {
            return $this->respond([
                "code"    => 401,
                "message" => [
                    "error" => lang("Message.message_10")
                ],
            ], 401);
        }
        return $this->respond([
            "code" => 200,
            "token" => $this->create_auth_tokens($sign->user)
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Create JWT tokens
     * @param string $userId
     * @return array
     */
    private function create_auth_tokens(string $userId): array
    {
        $time = strtotime(date("d-m-Y H:i:s"));
        $payload_a = [
            "iss"  => env("jwt.site.issuer"),
            "aud"  => env("jwt.site.audience"),
            "iat"  => $time,
            "nbf"  => $time,
            "exp"  => strtotime("+".env("jwt.time.exp.access")." minutes", $time),
            "type" => "access",
            "user" => (int) $userId
        ];
        $access = JWT::encode($payload_a, env("jwt.secret.key.access"));
        $payload_b = [
            "iss"  => env("jwt.site.issuer"),
            "aud"  => env("jwt.site.audience"),
            "iat"  => $time,
            "nbf"  => $time,
            "exp"  => strtotime("+".env("jwt.time.exp.refresh")." minutes", $time),
            "type" => "refresh",
            "user" => (int) $userId
        ];
        $refresh = JWT::encode($payload_b, env("jwt.secret.key.refresh"));
        return [
            "access"  => $access,
            "refresh" => $refresh
        ];
    }

    /**
     * Send reset password link
     * @param $token
     * @param $email
     * @return void
     */
    private function send_reset_email($token, $email)
    {
        $site_url = $this->settings->get_config("site_url");
        $emailVariables = [
            "{LINK}",
            "{SITE_URL}",
            "{SITE_NAME}",
            "{SITE_LOGO}"
        ];
        $codeVariable = [
            $site_url."auth/reset/?token=".$token,
            $site_url,
            $this->settings->get_config("site_name"),
            base_url("static/".$this->settings->get_config("site_logo"))
        ];
        $str = file_get_contents(WRITEPATH."emails/reset.html");
        $content = str_replace($emailVariables, $codeVariable, $str);
        $subject = lang("Fields.field_4");
        $this->notification->send($email, $subject, $content);
    }

    /**
     * Send new password
     * @param $password
     * @param $email
     * @return void
     */
    private function send_pass_email($password, $email)
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
     * Get validation rules for reset password
     * @return array
     */
    private function reset_validation_type(): array
    {
        return [
            "email" => ["label" => lang("Fields.field_1"),  "rules" => "required|valid_email|max_length[100]"]
        ];
    }

    /**
     * Get validation rules for sign in
     * @return array
     */
    private function login_validation_type(): array
    {
        return [
            "email"     => ["label" => lang("Fields.field_1"), "rules" => "required|valid_email|max_length[100]"],
            "password"  => ["label" => lang("Fields.field_2"), "rules" => "required|max_length[100]|alpha_numeric"]
        ];
    }

    /**
     * Get validation rules for sign up
     * @return array
     */
    private function register_validation_type(): array
    {
        return [
            "email"        => ["label" => lang("Fields.field_1"), "rules" => "required|valid_email|max_length[100]"],
            "password"     => ["label" => lang("Fields.field_2"), "rules" => "required|max_length[100]|alpha_numeric"],
            "re_password"  => ["label" => lang("Fields.field_3"), "rules" => "required|required|matches[password]"]
        ];
    }

    /**
     * Get validation rules for Google sign in
     * @return array
     */
    private function google_validation_type(): array
    {
        return [
            "id_token" => ["label" => lang("Fields.field_82"), "rules" => "required|min_length[20]"],
        ];
    }

    /**
     * Decode JWT auth token
     * @param string $token
     * @return object|null
     */
    private function decodeJWT(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, env('jwt.secret.key.refresh'), array('HS256'));
        } catch (SignatureInvalidException|UnexpectedValueException $ex) {
            $decoded = null;
        }
        return $decoded;
    }
}