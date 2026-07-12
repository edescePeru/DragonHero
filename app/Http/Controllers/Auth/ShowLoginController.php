<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

class ShowLoginController extends Controller
{
    public function __invoke()
    {
        return view('auth-login');
    }
}
