<?php namespace App\Controllers\Api\Account;

require_once(APPPATH . 'ThirdParty/Stripe/init.php');

use App\Controllers\PrivateController;
use App\Models\PlansModel;
use App\Models\AppsModel;
use App\Models\TransactionsModel;
use App\Models\DepositMethodsModel;
use App\Libraries\Uid;
use App\Libraries\Settings;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use Exception;
use ReflectionException;
use Stripe\Charge;
use Stripe\Stripe;
use Config\Services;

class Deposit extends PrivateController
{
    private $plans;
    private $apps;
    private $transactions;
    private $uid;
    private $settings;
    private $methods;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->plans = new PlansModel();
        $this->apps = new AppsModel();
        $this->transactions = new TransactionsModel();
        $this->uid = new Uid();
        $this->settings = new Settings();
        $this->methods = new DepositMethodsModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get all active plans
     * @return mixed
     */
    public function plans()
    {
        $items = $this->plans->findAll();
        $list = [];
        $currency_code = $this->settings->get_config("currency_code");
        $currency_symbol = $this->settings->get_config("currency_symbol");
        foreach ($items as $item) {
            $list[] = [
                "id"       => (int) $item["id"],
                "count"    => (int) $item["count"],
                "price"    => $item["price"],
                "save"     => $item["save"],
                "currency" => $currency_code,
                "symbol"   => $currency_symbol
            ];
        }
        return $this->respond([
            "code" => 200,
            "list" => $list,
        ], 200);
    }

    /**
     * Get all active deposit methods
     * @return mixed
     */
    public function methods()
    {
        $items = $this->methods
            ->where("status", 1)
            ->findAll();
        $list = [];
        foreach ($items as $item) {
            $list[] = [
                "id"          => (int) $item["id"],
                "name"        => $item["name"],
                "logo"        => base_url("theme/bootstrap/img/deposit/".$item["logo"]),
                "api_value_1" => $item["api_value_1"],
                "api_value_2" => $item["id"] == 2 ? $item["api_value_2"] : "hide",
            ];
        }
        return $this->respond([
            "code" => 200,
            "list" => $list,
        ], 200);
    }

    /**
     * Start razorpay create order
     * @param string $reference
     * @return mixed
     * @throws ReflectionException
     */
    public function paystack_make_pay(string $reference = "")
    {
        $client = Services::curlrequest();
        $method = $this->methods
            ->where("id", 4)
            ->select("api_value_1,api_value_2")
            ->first();
        try {
            $response = $client->request('GET', 'https://api.paystack.co/transaction/verify/'.esc($reference), [
                "headers" => [
                    'Content-Type'  => 'application/json',
                    "Authorization" => "Bearer ".$method["api_value_2"]
                ],
            ]);
            $array = json_decode($response->getBody());
            if ($array->data->status == "success" && !$this->duplicate_validation(esc($reference))) {
                $plan = $this->plans
                    ->where(["id" => (int) $array->data->metadata->plan])
                    ->first();
                $app = $this->apps
                    ->where(["uid" => esc($array->data->metadata->app)])
                    ->select("id,name,link,uid,balance")
                    ->first();
                $this->transactions->insert([
                    "uid"       => $reference,
                    "user_id"   => $this->user["id"],
                    "amount"    => $plan["price"],
                    "app_id"    => $app["id"],
                    "status"    => 1,
                    "method_id" => 1,
                    "quantity"  => $plan["count"]
                ]);
                $this->apps->update($app["id"], [
                    "status"  => 1,
                    "balance" => $app["balance"] + $plan["count"]
                ]);
                return $this->respond(["code" => 200], 200);
            } else {
                return $this->respond([
                    "code"    => 503,
                    "message" => [
                        "error" => lang("Message.message_61")
                    ],
                ], 400);
            }
        } catch (HTTPException $e) {
            return $this->respond([
                "code"    => 503,
                "message" => [
                    "error" => lang("Message.message_61")
                ],
            ], 400);
        }
    }

    /**
     * Start razorpay create order
     * @param string $uid
     * @param int $plan_id
     * @return mixed
     */
    public function create_order_razorpay(string $uid = "", int $plan_id = 0)
    {
        $client = Services::curlrequest();

        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
            ->select("uid")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $method = $this->methods
            ->where("id", 3)
            ->select("api_value_1,api_value_2")
            ->first();
        $plan = $this->plans
            ->where(["id" => $plan_id])
            ->first();
        if (!$plan) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_48")
                ],
            ], 400);
        }
        try {
            $response = $client->request('POST', 'https://api.razorpay.com/v1/orders', [
                "auth" => [
                    $method["api_value_1"],
                    $method["api_value_2"]
                ],
                "headers" => [
                    'Content-Type' => 'application/json',
                ],
                "json" => [
                    "amount"   => $plan["price"] * 100,
                    "currency" => $this->settings->get_config("currency_code"),
                    "notes"    => [
                        "plan" => $plan["id"],
                        "app"  => $app["uid"]
                    ]
                ]
            ]);
            $array = json_decode($response->getBody());
            return $this->respond([
                "code" => 200,
                "id"   => $array->id
            ], 200);
        } catch (HTTPException $e) {
            return $this->respond([
                "code"    => 503,
                "message" => [
                    "error" => lang("Message.message_61")
                ],
            ], 400);
        }
    }

    /**
     * Razorpay make pay
     * @return mixed
     */
    public function razorpay_make_pay()
    {
        if (!$this->validate($this->razorpay_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $order_id = esc($this->request->getPost("order_id"));
        $razorpay_payment_id = esc($this->request->getPost("razorpay_payment_id"));
        $razorpay_signature = esc($this->request->getPost("razorpay_signature"));
        $method = $this->methods
            ->where("id", 3)
            ->select("api_value_1,api_value_2")
            ->first();
        $generated_signature = hash_hmac('sha256', $order_id.'|'.$razorpay_payment_id, $method["api_value_2"]);
        if ($generated_signature != $razorpay_signature) {
            return $this->respond([
                "code"    => 503,
                "message" => [
                    "error" => lang("Message.message_61")
                ],
            ], 400);
        }
        $isValid = $this->create_razor_transactions($method, $order_id, $razorpay_payment_id);
        if (!$isValid) {
            return $this->respond([
                "code"    => 503,
                "message" => [
                    "error" => lang("Message.message_61")
                ],
            ], 503);
        }
        return $this->respond(["code" => 200], 200);
    }

    /**
     * Start Stripe payment
     * @param string $uid
     * @param int $plan_id
     * @return mixed
     */
    public function make_pay(string $uid = "", int $plan_id = 0)
    {
        if (!$this->validate($this->stripe_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $app = $this->apps
            ->where(["uid" => esc($uid), "user" => $this->user["id"]])
            ->select("id,name,link,uid,balance")
            ->first();
        if (!$app) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_14")
                ],
            ], 400);
        }
        $plan = $this->plans
            ->where(["id" => $plan_id])
            ->first();
        if (!$plan) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_48")
                ],
            ], 400);
        }
        $method = $this->methods
            ->where("id", 1)
            ->select("api_value_2")
            ->first();
        Stripe::setApiKey($method["api_value_2"]);
        try {
            Charge::create([
                'amount'      => $plan["price"] * 100,
                'currency'    => $this->settings->get_config("currency_code"),
                'description' => $plan["count"]." ".lang("Fields.field_120")." ".$app["name"],
                'source'      => esc($this->request->getPost("token")),
            ]);
            $this->transactions->insert([
                "uid"       => $this->uid->create(),
                "user_id"   => $this->user["id"],
                "amount"    => $plan["price"],
                "app_id"    => $app["id"],
                "status"    => 1,
                "method_id" => 1,
                "quantity"  => $plan["count"]
            ]);
            $this->apps->update($app["id"], [
                "status"  => 1,
                "balance" => $app["balance"] + $plan["count"]
            ]);
            return $this->respond([
                "code" => 200
            ], 200);
        } catch (Exception $e) {
            return $this->respond([
                "code"    => 503,
                "message" => [
                    "error" => lang("Message.message_61")
                ],
            ], 503);
        }
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Create Razorpay transactions
     * @param array $method
     * @param string $order_id
     * @param string $payment_id
     * @return bool
     */
    private function create_razor_transactions(array $method, string $order_id, string $payment_id): bool
    {
        $client = Services::curlrequest();
        try {
            $response = $client->request('GET', 'https://api.razorpay.com/v1/orders/'.$order_id, [
                "auth" => [
                    $method["api_value_1"],
                    $method["api_value_2"]
                ],
                "headers" => [
                    'Content-Type' => 'application/json',
                ],
            ]);
            $data = json_decode($response->getBody());
            if ($data->status == "paid" && !$this->duplicate_validation($payment_id)) {
                $plan = $this->plans
                    ->where(["id" => (int) $data->notes->plan])
                    ->first();
                $app = $this->apps
                    ->where(["uid" => esc($data->notes->app)])
                    ->select("id,name,link,uid,balance")
                    ->first();
                $this->transactions->insert([
                    "uid"       => $payment_id,
                    "user_id"   => $this->user["id"],
                    "amount"    => $plan["price"],
                    "app_id"    => $app["id"],
                    "status"    => 1,
                    "method_id" => 1,
                    "quantity"  => $plan["count"]
                ]);
                $this->apps->update($app["id"], [
                    "status"  => 1,
                    "balance" => $app["balance"] + $plan["count"]
                ]);
                return true;
            } else {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Check duplicate validation
     * @param string $transactionUID
     * @return bool
     */
    private function duplicate_validation(string $transactionUID): bool
    {
        if (!$this->transactions
        ->where("uid", $transactionUID)
        ->countAllResults()) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get validation rules for Stripe payment
     * @return array
     */
    private function stripe_validation_type(): array
    {
        return [
            "token" => ["label" => lang("Fields.field_120"), "rules" => "required|max_length[500]"],
        ];
    }

    /**
     * Get validation rules for Razorpay payment
     * @return array
     */
    private function razorpay_validation_type(): array
    {
        return [
            "order_id"            => ["label" => lang("Fields.field_128"), "rules" => "required|max_length[500]"],
            "razorpay_payment_id" => ["label" => lang("Fields.field_129"), "rules" => "required|max_length[500]"],
            "razorpay_signature"  => ["label" => lang("Fields.field_130"), "rules" => "required|max_length[500]"],
        ];
    }
}