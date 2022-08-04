<?php namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;
use App\Libraries\Settings;
use App\Models\UsersModel;
use App\Models\TransactionsModel;
use App\Models\BuildsModel;
use App\Models\AppsModel;
use Exception;

class Stat extends PrivateController
{

    private $users;
    private $transactions;
    private $builds;
    private $apps;
    private $settings;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->users = new UsersModel();
        $this->transactions = new TransactionsModel();
        $this->builds = new BuildsModel();
        $this->apps = new AppsModel();
        $this->settings = new Settings();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get total counts
     * @return mixed
     */
    public function counters()
    {
        return $this->respond([
            "code"      => 200,
            "users"     => $this->users->countAllResults(),
            "apps"      => $this->apps->countAllResults(),
            "paid_apps" => $this->apps
                ->where("status", 1)
                ->countAllResults(),
            "builds"    => $this->builds->countAllResults()
        ], 200);
    }

    /**
     * Get total counts
     * @param int $period
     * 0 - last month
     * 1 - last year
     * @return mixed
     * @throws Exception
     */
    public function chart(int $period = 0)
    {
        if (!$period) {
            $min_date_year = strtotime(date('d-m-Y',strtotime('first day of this month')));
            $max_date_year = strtotime(date('d-m-Y',strtotime('last day of this month')));
        } else {
            $min_date_year = strtotime(date('d-m-Y',strtotime('first day of this year')));
            $max_date_year = strtotime(date('d-m-Y',strtotime('last day of this year')));
        }
        $list = $this->transactions
            ->where('created_at between '.$min_date_year.' and '.$max_date_year)
            ->findAll();
        $count = $this->transactions
            ->where('created_at between '.$min_date_year.' and '.$max_date_year)
            ->countAllResults();
        $builds = $this->builds
            ->where('created_at between '.$min_date_year.' and '.$max_date_year)
            ->countAllResults();
        $amount = 0;
        $datasets = [];
        $labels = [];
        if (!$period) {
            for ($i = 1; $i <= date('t'); $i ++) {
                $labels[] = $i;
                $datasets[$i] = 0;
                foreach ($list as $item) {
                    $day = date('d', $item["created_at"]);
                    if ($day == $i) {
                        $datasets[$i] = $datasets[$i] + $item["amount"];
                    }
                }
                $amount = $amount + $datasets[$i];
            }
        } else {
            for ($i = 1; $i <= 12; $i ++) {
                $key = $this->get_month($i);
                $labels[] = $key;
                $datasets[$key] = 0;
                foreach ($list as $item) {
                    $month = date('m', $item["created_at"]);
                    if ($month == $i) {
                        $datasets[$key] = $datasets[$key] + $item["amount"];
                    }
                }
                $amount = $amount + $datasets[$key];
            }
        }
        return $this->respond([
            "code"     => 200,
            "labels"   => $labels,
            "data"     => $datasets,
            "total"    => $count,
            "amount"   => $amount,
            "builds"   => $builds,
            "currency" => $this->settings->get_config("currency_symbol")
        ], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get month name
     * @param int $number
     * @return string
     */
    private function get_month(int $number): string
    {
        switch ($number) {
            case 1:
                return lang("Fields.field_108");
            case 2:
                return lang("Fields.field_109");
            case 3:
                return lang("Fields.field_110");
            case 4:
                return lang("Fields.field_111");
            case 5:
                return lang("Fields.field_112");
            case 6:
                return lang("Fields.field_113");
            case 7:
                return lang("Fields.field_114");
            case 8:
                return lang("Fields.field_115");
            case 9:
                return lang("Fields.field_116");
            case 10:
                return lang("Fields.field_117");
            case 11:
                return lang("Fields.field_118");
            case 12:
                return lang("Fields.field_119");
            default:
                return "-";
        }
    }
}