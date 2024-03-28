<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // $email = $request->input('email');
        $password = $request->input('password');

        // Hardcoded email and password for demonstration
        // $hardcodedEmail = 'admin@email.com';
        $hardcodedPassword = 'admin123';

        if ( $password === $hardcodedPassword) {
            // Authentication passed
            return redirect('/create'); // Change this URL to the desired destination after login
        }

        // Authentication failed
        return back()->withErrors(['email' => 'Invalid credentials']);
    }
}

?>