<?php namespace App\Controllers;

use App\Models\UsersModel;
use App\Models\SettingsModel;
use App\Libraries\Authorization\Passport;
use App\Libraries\Uid;
use Config\Services;
use Exception;
use mysqli;
use ReflectionException;

class Install extends BaseController
{
    private $passport;
    private $uid;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->passport = new Passport(12, false);
        $this->uid = new Uid();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Step 1. MySQL connection
     * @return mixed
     */
    public function index()
    {
        helper('filesystem');
        $env = get_file_info(ROOTPATH.'.env');
        if (!empty($env)) {
            return redirect()->to('install/step_2');
        }
        $dataPage = [

        ];
        return view('install/step_1', $dataPage);
    }

    /**
     * Step 2. External API
     * @return string
     */
    public function step_2(): string
    {
        $dataPage = [

        ];
        return view('install/step_2', $dataPage);
    }

    /**
     * Step 3. Create admin access
     * @return string
     */
    public function step_3(): string
    {
        $dataPage = [

        ];
        return view('install/step_3', $dataPage);
    }

    /**
     * Step 4. Finish
     * @return string
     */
    public function finish(): string
    {
        $dataPage = [

        ];
        return view('install/finish', $dataPage);
    }

    /**
     * Install database
     * @return mixed
     */
    public function db_install()
    {
        if (!$this->validate($this->mysql_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        try {
            $mysqli = new mysqli(
                esc($this->request->getPost("hostname")),
                esc($this->request->getPost("username")),
                esc($this->request->getPost("password")),
                esc($this->request->getPost("name")),
                (int) $this->request->getPost("port")
            );
            $sql = file_get_contents( WRITEPATH.'install/db.sql');
            $mysqli->multi_query($sql);
            helper('filesystem');
            $fileVariables = [
                '{BASE_URL}',
                '{DB_HOSTNAME}',
                '{DB_NAME}',
                '{DB_USER}',
                '{DB_PASSWORD}',
                '{DB_PORT}',
                '{JWT_ACCESS}',
                '{JWT_REFRESH}',
            ];
            $codeVariable = [
                esc($this->request->getPost("url"))."backend/",
                esc($this->request->getPost("hostname")),
                esc($this->request->getPost("name")),
                esc($this->request->getPost("username")),
                esc($this->request->getPost("password")),
                (int) $this->request->getPost("port"),
                hash('sha256', $this->uid->create()),
                hash('sha256', $this->uid->create()),
            ];
            $content = str_replace(
                $fileVariables,
                $codeVariable,
                file_get_contents(WRITEPATH.'install/.env')
            );
            write_file(ROOTPATH.'.env', $content);
            return $this->respond(["code" => 200], 200);
        } catch (Exception $e ) {
            return $this->respond([
                "code" => 400,
                "message" => [
                    "error" => lang("Message.message_64")
                ]
            ], 400);
        }
    }

    /**
     * Create API connection
     * @return mixed
     * @throws ReflectionException
     */
    public function api_connect()
    {
        if (!$this->validate($this->api_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $g_status = $this->github_token_validation(
            esc($this->request->getPost("git_token")),
            esc($this->request->getPost("git_username")),
            esc($this->request->getPost("git_repo")),
        );
        if (!$g_status) {
            return $this->respond([
                "code" => 400,
                "message" => [
                    "error" => lang("Message.message_65")
                ]
            ], 400);
        }
        $c_status = $this->codemagic_token_validation(
            esc($this->request->getPost("cm_token")),
            esc($this->request->getPost("cm_id")),
        );
        if (!$c_status) {
            return $this->respond([
                "code" => 400,
                "message" => [
                    "error" => lang("Message.message_66")
                ]
            ], 400);
        }
        $settings = new SettingsModel();
        $settings->update("github_repo", [
            "value" => esc($this->request->getPost("git_repo"))
        ]);
        $settings->update("github_token", [
            "value" => esc($this->request->getPost("git_token"))
        ]);
        $settings->update("github_username", [
            "value" => esc($this->request->getPost("git_username"))
        ]);
        $settings->update("codemagic_id", [
            "value" => esc($this->request->getPost("cm_id"))
        ]);
        $settings->update("codemagic_key", [
            "value" => esc($this->request->getPost("cm_token"))
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**
     * Create admin account
     * @return mixed
     * @throws ReflectionException
     */
    public function create_admin()
    {
        if (!$this->validate($this->admin_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $users = new UsersModel();
        $users->insert([
            "email"    => esc($this->request->getPost("email")),
            "password" => $this->passport->HashPassword(
                esc($this->request->getPost("password"))
            ),
            "admin"    => 1
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Check data for Codemagic connection
     * @param string $token
     * @param string $id
     * @return bool
     */
    private function codemagic_token_validation(string $token, string $id) :bool
    {
        $client = Services::curlrequest();
        try {
            $client->request('GET', 'https://api.codemagic.io/apps/'.$id, [
                'headers' => [
                    'x-auth-token' => $token,
                ],
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check API token for Github access
     * @param string $token
     * @param string $username
     * @param string $repo
     * @return bool
     */
    private function github_token_validation(string $token, string $username, string $repo) :bool
    {
        $client = Services::curlrequest();
        try {
            $client->request('GET', 'https://api.github.com/repos/'.$username.'/'.$repo, [
                'headers' => [
                    'authorization' => 'token '.$token,
                    'User-Agent'    => 'SiteNative Server',
                    'Accept'        => 'application/vnd.github.v3+json',
                ],
            ]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get validation rules for mysql database
     * @return array
     */
    private function mysql_validation_type(): array
    {
        return [
            "name"     => ["label" => lang("Install.install_4"), "rules" => "required|min_length[3]|max_length[100]"],
            "hostname" => ["label" => lang("Install.install_5"), "rules" => "required|min_length[3]|max_length[100]"],
            "username" => ["label" => lang("Install.install_6"), "rules" => "required|min_length[3]|max_length[100]"],
            "password" => ["label" => lang("Install.install_7"), "rules" => "required|min_length[3]|max_length[100]"],
            "port"     => ["label" => lang("Install.install_8"), "rules" => "required|numeric"],
            "url"      => ["label" => lang("Install.install_29"), "rules" => "required|min_length[3]|max_length[100]"],
        ];
    }
    /**
     * Get validation rules for API form
     * @return array
     */
    private function api_validation_type(): array
    {
        return [
            "git_username" => ["label" => lang("Install.install_21"), "rules" => "required|min_length[3]|max_length[100]"],
            "git_token"    => ["label" => lang("Install.install_18"), "rules" => "required|min_length[3]|max_length[100]"],
            "cm_token"     => ["label" => lang("Install.install_18"), "rules" => "required|min_length[3]|max_length[100]"],
            "cm_id"     => ["label" => lang("Install.install_20"), "rules" => "required|min_length[3]|max_length[100]"],
        ];
    }

    /**
     * Get validation rules for create admin
     * @return array
     */
    private function admin_validation_type(): array
    {
        return [
            "email"        => ["label" => lang("Install.install_23"), "rules" => "required|valid_email|max_length[100]"],
            "password"     => ["label" => lang("Install.install_24"), "rules" => "required|max_length[100]|alpha_numeric"],
            "re_password"  => ["label" => lang("Install.install_25"), "rules" => "required|required|matches[password]"]
        ];
    }
}
