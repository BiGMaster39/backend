<?php
namespace App\Controllers\Api\Admin;

use App\Controllers\PrivateController;

class Configuration extends PrivateController
{
    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get language pack for admin
     * @return array
     */
    public function language(): array
    {
        $data = [
            "code"   => 200,
            "result" => lang('Admin.lang')
        ];
        return $this->respond($data, $data['code']);
    }
}