<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\SignsAndroidModel;
use App\Models\SignsIosModel;
use App\Libraries\Uid;
use App\Libraries\Packer\Keystore;
use CodeIgniter\Config\Services;
use ReflectionException;

class Signs extends PrivateController
{
    private $android_signs;
    private $ios_signs;
    private $uid;
    private $keystore;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->android_signs = new SignsAndroidModel();
        $this->ios_signs = new SignsIosModel();
        $this->uid = new Uid();
        $this->keystore = new Keystore();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get short list signs for select in build android
     * @return mixed
     */
    public function short_list_android()
    {
        $signs = $this->android_signs
            ->where("user_id", $this->user["id"])
            ->select("uid,name")
            ->findAll();
        $list = [];
        foreach ($signs as $sign) {
            $list[] = [
                "value"     => $sign["uid"],
                "text"    => $sign["name"],
            ];
        }
        return $this->respond([
            "code"  => 200,
            "list"  => $list
        ], 200);
    }

    /**
     * Get short list signs for select in build ios
     * @return mixed
     */
    public function short_list_ios()
    {
        $signs = $this->ios_signs
            ->where("user_id", $this->user["id"])
            ->select("uid,name")
            ->findAll();
        $list = [];
        foreach ($signs as $sign) {
            $list[] = [
                "value" => $sign["uid"],
                "text"  => $sign["name"],
            ];
        }
        return $this->respond([
            "code"  => 200,
            "list"  => $list
        ], 200);
    }

    /**
     * Get list android signs
     * @return mixed
     */
    public function list_android()
    {
        $signs = $this->android_signs
            ->where("user_id", $this->user["id"])
            ->select("uid,name,alias,created_at")
            ->findAll();
        $list = [];
        foreach ($signs as $sign) {
            $list[] = [
                "uid"     => $sign["uid"],
                "name"    => $sign["name"],
                "alias"   => $sign["alias"],
                "created" => date('d-m-Y H:i', $sign['created_at']),
                "type"    => "android",
                "loading" => false
            ];
        }
        return $this->respond([
            "code"  => 200,
            "list"  => $list
        ], 200);
    }

    /**
     * Create keystore
     * @return mixed
     * @throws ReflectionException
     */
    public function create_keystore()
    {
        $encrypter = Services::encrypter();
        if (!$this->validate($this->jks_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $signing = $this->keystore->generate_android();
        if (!$signing["event"]) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_70").$signing["error"]
                ],
            ], 400);
        }
        $uid = $this->uid->create();
        $this->android_signs->insert([
            "name"              => esc($this->request->getPost("name")),
            "alias"             => $signing["alias"],
            "user_id"           => $this->user["id"],
            "uid"               => $uid,
            "keystore_password" => $encrypter->encrypt($signing["password"]),
            "key_password"      => $encrypter->encrypt($signing["password"]),
            "file"              => $signing["name"].'.jks'
        ]);
        return $this->respond([
            "code"    => 200,
            "item" => [
                "uid"     => $uid,
                "name"    => esc($this->request->getPost("name")),
                "alias"   => $signing["alias"],
                "created" => date('d-m-Y H:i'),
                "type"    => "android",
                "loading" => false
            ]
        ], 200);
    }

    /**
     * Upload keystore
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_keystore()
    {
        helper('filesystem');
        $encrypter = Services::encrypter();
        if (!$this->validate($this->android_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $keystore = $this->request->getFile('keystore');
        $name = $keystore->getRandomName();
        $keystore->move(WRITEPATH.'storage/android/', $name);
        $uid = $this->uid->create();
        $this->android_signs->insert([
            "name"              => esc($this->request->getPost("name")),
            "alias"             => esc($this->request->getPost("alias")),
            "user_id"           => $this->user["id"],
            "uid"               => $uid,
            "keystore_password" => $encrypter->encrypt(
                esc($this->request->getPost("keystore_password"))
            ),
            "key_password"      => $encrypter->encrypt(
                esc($this->request->getPost("key_password"))
            ),
            "file"              => $name
        ]);
        return $this->respond([
            "code"   => 200,
            "item"   => [
                "name"    => esc($this->request->getPost("name")),
                "alias"   => esc($this->request->getPost("alias")),
                "uid"     => $uid,
                "created" => date('d-m-Y H:i'),
                "type"    => "android",
                "loading" => false
            ]
        ], 200);
    }

    /**
     * Remove android item
     * @param string $uid
     * @return mixed
     */
    public function remove_android(string $uid = "")
    {
        $item = $this->android_signs
            ->where(["uid" => esc($uid), "user_id" => $this->user["id"]])
            ->select("id,file")
            ->first();
        if (!$item) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_28")
                ],
            ], 400);
        }
        $this->android_signs->delete($item["id"]);
        unlink(WRITEPATH.'storage/android/'.$item["file"]);
        return $this->respond([
            "code"   => 200,
        ], 200);
    }

    /**
     * Get list ios signs
     * @return mixed
     */
    public function list_ios()
    {
        $signs = $this->ios_signs
            ->where("user_id", $this->user["id"])
            ->select("uid,name,issuer_id,created_at")
            ->findAll();
        $list = [];
        foreach ($signs as $sign) {
            $list[] = [
                "uid"       => $sign["uid"],
                "name"      => $sign["name"],
                "issuer_id" => $sign["issuer_id"],
                "created"   => date('d-m-Y H:i', $sign['created_at']),
                "type"      => "ios",
                "loading"   => false
            ];
        }
        return $this->respond([
            "code"  => 200,
            "list"  => $list
        ], 200);
    }

    /**
     * Upload ios cert
     * @return mixed
     * @throws ReflectionException
     */
    public function upload_cert()
    {
        helper('filesystem');
        if (!$this->validate($this->ios_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $cert = $this->request->getFile('api_key');
        $name = $cert->getRandomName();
        $cert->move(WRITEPATH.'storage/ios/', $name);
        $uid = $this->uid->create();

        $pub = $this->request->getFile('cert');
        $pub->move(WRITEPATH.'storage/pub/', $uid);

        $this->ios_signs->insert([
            "name"      => esc($this->request->getPost("name")),
            "issuer_id" => esc($this->request->getPost("issuer_id")),
            "key_id"    => esc($this->request->getPost("key_id")),
            "user_id"   => $this->user["id"],
            "uid"       => $uid,
            "file"      => $name
        ]);
        return $this->respond([
            "code"   => 200,
            "item"   => [
                "name"      => esc($this->request->getPost("name")),
                "issuer_id" => esc($this->request->getPost("issuer_id")),
                "key_id"    => esc($this->request->getPost("key_id")),
                "created"   => date('d-m-Y H:i'),
                "type"      => "ios",
                "loading"   => false
            ]
        ], 200);
    }

    /**
     * Remove ios item
     * @param string $uid
     * @return mixed
     */
    public function remove_ios(string $uid = "")
    {
        $item = $this->ios_signs
            ->where(["uid" => esc($uid), "user_id" => $this->user["id"]])
            ->select("id,file")
            ->first();
        if (!$item) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_28")
                ],
            ], 400);
        }
        $this->ios_signs->delete($item["id"]);
        unlink(WRITEPATH.'storage/ios/'.$item["file"]);
        return $this->respond([
            "code"   => 200,
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules upload keystore
     * @return array
     */
    private function android_validation_type(): array
    {
        return [
            "name"              => ["label" => lang("Fields.field_61"),  "rules" => "required|min_length[2]|max_length[100]"],
            "alias"             => ["label" => lang("Fields.field_62"),  "rules" => "required|min_length[2]|max_length[100]"],
            "keystore_password" => ["label" => lang("Fields.field_63"),  "rules" => "required|min_length[2]|max_length[100]"],
            "key_password"      => ["label" => lang("Fields.field_64"),  "rules" => "required|min_length[2]|max_length[100]"],
            'keystore'          => ['label' => lang("Fields.field_65"),  'rules' => 'uploaded[keystore]|max_size[keystore,500]|ext_in[keystore,jks]'],
        ];
    }

    /**
     * Get validation rules create keystore
     * @return array
     */
    private function jks_validation_type(): array
    {
        return [
            "name" => ["label" => lang("Fields.field_61"),  "rules" => "required|min_length[2]|max_length[100]"],
        ];
    }

    /**
     * Get validation rules upload ios cert
     * @return array
     */
    private function ios_validation_type(): array
    {
        return [
            "name"      => ["label" => lang("Fields.field_61"),  "rules" => "required|min_length[2]|max_length[100]"],
            "issuer_id" => ["label" => lang("Fields.field_66"),  "rules" => "required|min_length[2]|max_length[100]"],
            "key_id"    => ["label" => lang("Fields.field_67"),  "rules" => "required|min_length[2]|max_length[100]"],
            'api_key'   => ['label' => lang("Fields.field_68"),  'rules' => 'uploaded[api_key]|max_size[api_key,500]|ext_in[api_key,p8]'],
            'cert'      => ['label' => lang("Fields.field_127"),  'rules' => 'uploaded[cert]|max_size[cert,500]'],
        ];
    }
}