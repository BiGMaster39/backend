<?php namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\DepositMethodsModel;
use ReflectionException;

class Deposit extends PrivateController
{
    private $methods;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->methods = new DepositMethodsModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get all deposit methods
     * @return mixed
     */
    public function get()
    {
        $items = $this->methods->findAll();
        $list = [];
        foreach ($items as $item) {
            $list[] = [
                "id"          => $item["id"],
                "name"        => $item["name"],
                "logo"        => base_url("theme/bootstrap/img/deposit/".$item["logo"]),
                "api_value_1" => $item["api_value_1"],
                "api_value_2" => $item["api_value_2"],
                "api_value_3" => $item["api_value_3"],
                "status"      => (int) $item["status"],
            ];
        }
        return $this->respond([
            "code" => 200,
            "list" => $list,
        ], 200);
    }

    /**
     * Update deposit methods
     * @return mixed
     * @throws ReflectionException
     */
    public function update(int $id = 0)
    {
        if (!$this->validate($this->method_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $method = $this->methods
            ->where("id", $id)
            ->select("id")
            ->first();
        if (!$method) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_69")
                ],
            ], 400);
        }
        $this->methods->update($method["id"], [
            "status"      => (int) $this->request->getPost("status"),
            "name"        => esc($this->request->getPost("name")),
            "api_value_1" => esc($this->request->getPost("api_value_1")),
            "api_value_2" => esc($this->request->getPost("api_value_2")),
            "api_value_3" => esc($this->request->getPost("api_value_3")),
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for create plan
     * @return array
     */
    private function method_validation_type()
    {
        return [
            "name"        => ["label" => lang("Fields.field_131"), "rules" => "required|max_length[1000]"],
            "status"      => ["label" => lang("Fields.field_132"), "rules" => "required|in_list[0,1]"],
            "api_value_1" => ["label" => lang("Fields.field_133"), "rules" => "max_length[1000]"],
            "api_value_2" => ["label" => lang("Fields.field_134"), "rules" => "max_length[1000]"],
            "api_value_3" => ["label" => lang("Fields.field_135"), "rules" => "max_length[1000]"],
        ];
    }
}