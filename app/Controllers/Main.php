<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use Codeigniter\HTTP\ResponseInterface;

class Main extends BaseController

{

    public function index()
    {
        return view('main/home');
    }


}