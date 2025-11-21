<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class New_loginController extends Controller
{
  
public function show()
{
    return view('auth.new_login'); // Create this view later
}
}
