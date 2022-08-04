<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\TransactionsModel;
use App\Models\AppsModel;
use App\Libraries\Settings;

define("LIMIT", 20);

class Transactions extends PrivateController
{
    private $transactions;
    private $apps;
    private $settings;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->transactions = new TransactionsModel();
        $this->apps = new AppsModel();
        $this->settings = new Settings();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list completed transactions
     * @param int $offset
     * @return mixed
     */
    public function list(int $offset = 0)
    {
        $transactions = $this->transactions
            ->where(["user_id" => $this->user["id"], "status" => 1])
            ->orderBy("id", "DESC")
            ->findAll(LIMIT, (int) $offset);
        $count = $this->transactions
            ->where(["user_id" => $this->user["id"], "status" => 1])
            ->countAllResults();
        $list = [];
        foreach ($transactions as $transaction) {
            $app = $this->apps
                ->where("id", $transaction["app_id"])
                ->select("uid,name")
                ->first();
            $list[] = [
                "uid"      => $transaction["uid"],
                "amount"   => $transaction["amount"],
                "quantity" => $transaction["quantity"],
                "created"  => date('d-m-Y H:i', $transaction['created_at']),
                "app"      => [
                    "name" => $app["name"],
                    "uid"  => $app["uid"]
                ]
            ];
        }
        return $this->respond([
            "list"     => $list,
            "currency" => [
                "code"   => $this->settings->get_config("currency_code"),
                "symbol" => $this->settings->get_config("currency_symbol"),
            ],
            "count"    => $count
        ], 200);
    }
}