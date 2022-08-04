<?php
namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\PlansModel;
use App\Libraries\Settings;
use ReflectionException;

define("LIMIT", 20);

class Plans extends PrivateController
{
    private $plans;
    private $settings;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->plans = new PlansModel();
        $this->settings = new Settings();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get plans
     * @return mixed
     */
    public function get()
    {
        $plans = $this->plans->findAll();
        $list = [];
        foreach ($plans as $item) {
            $list[] = [
                "id"    => (int) $item["id"],
                "count" => (int) $item["count"],
                "price" => $item["price"],
                "save"  => $item["save"],
            ];
        }
        return $this->respond([
            "code"     => 200,
            "list"     => $list,
            "currency" => $this->settings->get_config("currency_symbol")
        ], 200);
    }

    /**
     * Create plan
     * @return mixed
     * @throws ReflectionException
     */
    public function create()
    {
        if (!$this->validate($this->plan_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $id = $this->plans->insert([
            "count" => (int) $this->request->getPost("count"),
            "price" => esc($this->request->getPost("price")),
            "save"  => esc($this->request->getPost("save")),
        ]);
        return $this->respond(["code" => 200, "id" => (int) $id], 200);
    }

    /**
     * Update plan
     * @return mixed
     * @throws ReflectionException
     */
    public function update($id = 0)
    {
        $plan = $this->plans
            ->where("id", (int) $id)
            ->select("id")
            ->first();
        if (!$plan) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_59")
                ],
            ], 400);
        }
        if (!$this->validate($this->plan_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $this->plans->update($plan["id"], [
            "count" => (int) $this->request->getPost("count"),
            "price" => esc($this->request->getPost("price")),
            "save"  => esc($this->request->getPost("save")),
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**
     * Remove plan
     * @return mixed
     */
    public function remove($id = 0)
    {
        $plan = $this->plans
            ->where("id", (int) $id)
            ->select("id")
            ->first();
        if (!$plan) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_59")
                ],
            ], 400);
        }
        $this->plans->delete($plan["id"]);
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for create plan
     * @return array
     */
    private function plan_validation_type()
    {
        return [
            "count" => ["label" => lang("Fields.field_104"), "rules" => "required|numeric"],
            "price" => ["label" => lang("Fields.field_105"), "rules" => "required|numeric"],
            "save"  => ["label" => lang("Fields.field_106"), "rules" => "required|numeric"],
        ];
    }
}