<?php
namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Models\TransactionsModel;
use App\Models\AppsModel;
use App\Libraries\Settings;
use App\Models\UsersModel;

define("LIMIT", 20);

class Transactions extends PrivateController
{
    private $transactions;
    private $settings;
    private $apps;
    private $users;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->transactions = new TransactionsModel();
        $this->settings = new Settings();
        $this->apps = new AppsModel();
        $this->users = new UsersModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get transactions
     * @param int $offset
     * @return mixed
     */
    public function get(int $offset = 0)
    {
        $transactions = $this->transactions
            ->orderBy("id", "DESC")
            ->findAll(LIMIT, (int) $offset);
        $count = $this->transactions->countAllResults();
        $list = [];
        foreach ($transactions as $transaction) {
            $app = $this->apps
                ->where("id", $transaction["app_id"])
                ->select("uid,name")
                ->first();
            $user = $this->users
                ->where("id", $transaction["user_id"])
                ->select("id,email")
                ->first();
            $list[] = [
                "uid"      => $transaction["uid"],
                "amount"   => $transaction["amount"],
                "quantity" => $transaction["quantity"],
                "created"  => date('d-m-Y H:i', $transaction['created_at']),
                "app"      => [
                    "name" => $app["name"],
                    "uid"  => $app["uid"],
                    "icon" => $this->get_icon($app["uid"])
                ],
                "user"     => [
                    "email" => $user["email"],
                    "id"    => (int) $user["id"]
                ]
            ];
        }
        return $this->respond([
            "code"     => 200,
            "list"     => $list,
            "currency" => $this->settings->get_config("currency_symbol"),
            "count"    => (int) $count
        ], 200);
    }

    /**
     * Get transactions fro user
     * @param int $offset
     * @param int $userID
     * @return mixed
     */
    public function get_user(int $offset = 0, int $userID = 0)
    {
        $transactions = $this->transactions
            ->where("user_id", $userID)
            ->orderBy("id", "DESC")
            ->findAll(LIMIT, $offset);
        $count = $this->transactions
            ->where("user_id", $userID)
            ->countAllResults();
        $list = [];
        foreach ($transactions as $transaction) {
            $app = $this->apps
                ->where("id", $transaction["app_id"])
                ->select("uid,name")
                ->first();
            $user = $this->users
                ->where("id", $transaction["user_id"])
                ->select("id,email")
                ->first();
            $list[] = [
                "uid"      => $transaction["uid"],
                "amount"   => $transaction["amount"],
                "quantity" => $transaction["quantity"],
                "created"  => date('d-m-Y H:i', $transaction['created_at']),
                "app"      => [
                    "name" => $app["name"],
                    "uid"  => $app["uid"],
                    "icon" => $this->get_icon($app["uid"])
                ],
                "user"     => [
                    "email" => $user["email"],
                    "id"    => (int) $user["id"]
                ]
            ];
        }
        return $this->respond([
            "code"     => 200,
            "list"     => $list,
            "currency" => $this->settings->get_config("currency_symbol"),
            "count"    => (int) $count
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get app icon
     * @param string $uid
     * @return string|null
     */
    private function get_icon(string $uid): ?string
    {
        $isIcon = is_dir(ROOTPATH.'upload/icons/'.$uid);
        if ($isIcon) {
            $unix = strtotime(date('m/d/Y h:i:s a', time()));
            $icon = base_url("upload/icons/".$uid."/android/hdpi_72.png?".$unix);
        } else {
            $icon = null;
        }
        return $icon;
    }
}