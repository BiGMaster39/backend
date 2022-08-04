<?php

namespace App\Controllers;

use App\Libraries\Packer\Keystore;

class Home extends BaseController
{
	public function index()
	{
        return view('home/index');
	}

    public function test()
    {
        $keystore = new Keystore();

        $keystore->generate_android();

        return $this->respond([

        ], 200);
    }
}
