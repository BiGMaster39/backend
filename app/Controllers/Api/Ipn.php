<?php
namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\AppsModel;
use App\Models\TransactionsModel;
use App\Models\PlansModel;
use App\Models\DepositMethodsModel;
use ReflectionException;

class Ipn extends BaseController
{
    const VERIFY_URI = 'https://www.paypal.com/cgi-bin/webscr';
    const SANDBOX_VERIFY_URI = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

    private $apps;
    private $transactions;
    private $plans;
    private $methods;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->apps = new AppsModel();
        $this->transactions = new TransactionsModel();
        $this->plans = new PlansModel();
        $this->methods = new DepositMethodsModel();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get paypal notification about transaction
     * @return void
     * @throws ReflectionException
     */
    public function paypal()
    {
        $data = file_get_contents('php://input');
        $dataArray = explode('&', $data);
        $post = [];
        foreach ($dataArray as $key) {
            $key = explode ('=', $key);
            if (count($key) == 2)
                $post[$key[0]] = urldecode($key[1]);
        }
        $req = 'cmd=_notify-validate';
        foreach ($post as $key => $value) {
            $value = urlencode(stripslashes($value));
            $req .= "&$key=$value";
        }
        $method = $this->methods
            ->where("id", 2)
            ->select("api_value_2")
            ->first();
        $ch = curl_init($method["api_value_2"]);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req);
        curl_setopt($ch, CURLOPT_SSLVERSION, 6);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: PHP-IPN-Verification-Script',
            'Connection: Close',
        ));
        $res = curl_exec($ch);
        if (!$res) {
            curl_close($ch);
            exit;
        }
        curl_close($ch);
        if (strcmp($res, "VERIFIED") == 0) {
            $plan = $this->plans
                ->where(["id" => (int) $_POST['item_number']])
                ->first();
            if (!$plan) {
                exit;
            }
            $app = $this->apps
                ->where("uid", esc($_POST['custom']))
                ->first();
            if (!$app) {
                exit;
            }
            $amount = esc($_POST['mc_gross']);
            if ($amount != $plan["price"]) {
                exit;
            }
            $this->transactions->insert([
                "uid"       => esc($_POST['txn_id']),
                "user_id"   => $app["user"],
                "amount"    => esc($_POST['mc_gross']),
                "app_id"    => $app["id"],
                "status"    => 1,
                "method_id" => 2,
                "quantity"  => $plan["count"]
            ]);
            $this->apps->update($app['id'], [
                'balance'   => $app['balance'] + $plan["count"],
                'status'    => 1
            ]);
        }
        return $this->respond("OK", 200);
    }
}