<?php
namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\SettingsModel;
use App\Models\EmailConfigModel;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use Config\Services;
use ReflectionException;

define("LIMIT", 20);

class Settings extends PrivateController
{
    private $settings;
    private $emailConfig;
    private $licenseIssuer = "https://license.flangapp.com/backend/api/activation/flangapp/";

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->settings = new SettingsModel();
        $this->emailConfig = new EmailConfigModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get global settings
     * @return mixed
     */
    public function get()
    {
        $list = $this->settings->findAll();
        $settings = [];
        foreach ($list as $item) {
            $settings[$item["set_key"]] = $item["value"];
        }
        return $this->respond([
            "code" => 200,
            "list" => $settings,
        ], 200);
    }

    /**
     * Get global settings
     * @return mixed
     */
    public function email_config()
    {
        $config = $this->emailConfig
            ->where("id", 1)
            ->first();
        return $this->respond([
            "code"   => 200,
            "detail" => [
                "host"     => $config["host"],
                "user"     => $config["user"],
                "port"     => (int) $config["port"],
                "timeout"  => (int) $config["timeout"],
                "charset"  => $config["charset"],
                "sender"   => $config["sender"],
                "password" => $config["password"]
            ]
        ], 200);
    }

    /**
     * Update global settings
     * @return mixed
     * @throws ReflectionException
     */
    public function update_global()
    {
        if (!$this->validate($this->settings_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        foreach ($this->request->getPost() as $key => $val) {
            $this->settings->update($key, [
                "value" => esc($val)
            ]);
        }
        return $this->respond(["code" => 200], 200);
    }

    /**
     * Update activation license status
     * @return mixed
     * @throws ReflectionException
     */
    public function update_license()
    {
        if (!$this->validate($this->license_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $client = Services::curlrequest();
        $code = esc($this->request->getPost("code"));
        try {
            $client->request('GET', $this->licenseIssuer.$code, [
                "headers" => [
                    "User-Agent" => site_url()
                ],
            ]);
            $this->settings->update("license", [
                "value" => $code
            ]);
        } catch (HTTPException $e) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error"  => lang("Message.message_71"),
                    "detail" => $e->getMessage()
                ],
            ], 400);
        }
    }

    /**
     * Update email settings
     * @return mixed
     * @throws ReflectionException
     */
    public function update_email()
    {
        if (!$this->validate($this->email_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $this->emailConfig->update(1, [
            "host"     => esc($this->request->getPost("host")),
            "user"     => esc($this->request->getPost("user")),
            "port"     => (int) $this->request->getPost("port"),
            "timeout"  => (int) $this->request->getPost("timeout"),
            "charset"  => esc($this->request->getPost("charset")),
            "sender"   => esc($this->request->getPost("sender")),
            "password" => esc($this->request->getPost("password"))
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**
     * Upload settings logo
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_logo()
    {
        if (!$this->validate($this->update_logo_image_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $image = $this->request->getFile('logo');
        $name = $image->getRandomName();
        $image->move(ROOTPATH.'static', $name);
        $this->settings->update("site_logo", [
            "value" => $name
        ]);
        return $this->respond([
            "code" => 200,
            "logo" => base_url("static/".$name)
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for update email settings
     * @return array
     */
    private function email_validation_type(): array
    {
        return [
            "host"     => ["label" => lang("Fields.field_97"), "rules" => "required|min_length[3]|max_length[100]"],
            "user"     => ["label" => lang("Fields.field_98"), "rules" => "required|valid_email|max_length[100]"],
            "port"     => ["label" => lang("Fields.field_99"), "rules" => "required|numeric"],
            "timeout"  => ["label" => lang("Fields.field_100"), "rules" => "required|numeric"],
            "charset"  => ["label" => lang("Fields.field_101"), "rules" => "required|min_length[3]|max_length[10]"],
            "sender"   => ["label" => lang("Fields.field_102"), "rules" => "required|min_length[3]|max_length[100]"],
            "password" => ["label" => lang("Fields.field_103"), "rules" => "required|min_length[3]|max_length[100]"],
        ];
    }

    /**
     * Get validation rules for update main settings
     * @return array
     */
    private function settings_validation_type(): array
    {
        return [
            "site_name"          => ["label" => lang("Fields.field_83"),  "rules" => "required|min_length[3]|max_length[100]"],
            "site_url"           => ["label" => lang("Fields.field_84"),  "rules" => "required|min_length[3]|max_length[250]"],
            "currency_code"      => ["label" => lang("Fields.field_85"),  "rules" => "required|min_length[3]|max_length[3]"],
            "currency_symbol"    => ["label" => lang("Fields.field_86"),  "rules" => "required|min_length[1]|max_length[1]"],
            "github_username"    => ["label" => lang("Fields.field_87"),  "rules" => "required|min_length[8]|max_length[100]"],
            "github_token"       => ["label" => lang("Fields.field_88"),  "rules" => "required|min_length[8]|max_length[100]"],
            "github_repo"        => ["label" => lang("Fields.field_89"),  "rules" => "required|min_length[3]|max_length[100]"],
            "codemagic_key"      => ["label" => lang("Fields.field_90"),  "rules" => "required|min_length[8]|max_length[100]"],
            "codemagic_id"       => ["label" => lang("Fields.field_91"),  "rules" => "required|min_length[8]|max_length[100]"],
            "ionic_icons"        => ["label" => lang("Fields.field_94"),  "rules" => "required|min_length[8]|max_length[100]"],
            "google_id"          => ["label" => lang("Fields.field_95"),  "rules" => "max_length[100]"],
            "google_enabled"     => ["label" => lang("Fields.field_96"),  "rules" => "required|in_list[0,1]"],
        ];
    }

    /**
     * Get validation rules for upload logo
     * @return array
     */
    private function update_logo_image_validation_type(): array
    {
        return [
            'logo' => ['label' => lang("Fields.field_30"), 'rules' => 'uploaded[logo]|max_size[logo,500]|ext_in[logo,png,svg,jpg]|max_dims[logo,1200,1200]'],
        ];
    }

    /**
     * Get validation rules for update main settings
     * @return array
     */
    private function license_validation_type(): array
    {
        return [
            "code"  => ["label" => lang("Fields.field_136"),  "rules" => "required|min_length[3]|max_length[500]"],
        ];
    }

}