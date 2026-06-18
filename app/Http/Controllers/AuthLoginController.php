<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthLoginController extends Controller
{

    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
    dd($credentials);
        if (Auth::attempt($credentials)) {
            // Authentication passed...
            return redirect()->intended('/home');
        } else {
            return back()->withErrors(['email' => 'Invalid email or password']);
        }
    }
     
    protected $redirectTo = '/home';

}
?>
