<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SecureIvsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthLoginController extends Controller
{

    public function showLoginForm(Request $request, SecureIvsService $secureIvs)
    {
        if (Auth::check()) {
            return redirect()->intended(route('order-delivery.index'));
        }

        // If returning with session errors or explicit show request, display the error/retry page
        if ($request->session()->has('errors') || $request->has('error') || $request->boolean('show')) {
            return view('auth.login');
        }

        return $this->redirectToSecureIvs($request, $secureIvs);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('order-delivery.index'));
        }

        return back()
            ->withErrors(['email' => 'Invalid email or password.'])
            ->onlyInput('email');
    }

    public function redirectToSecureIvs(Request $request, SecureIvsService $secureIvs)
    {
        if (Auth::check()) {
            return redirect()->intended(route('order-delivery.index'));
        }

        $method = (string) $request->query('method', 'email');
        $callbackUrl = route('login.secureivs.callback');
        $redirectUrl = $secureIvs->getRedirectUrl($callbackUrl, $method);

        return redirect()->away($redirectUrl);
    }

    public function handleSecureIvsCallback(Request $request, SecureIvsService $secureIvs)
    {
        $token = (string) ($request->query('token') ?: $request->query('return_code'));

        if ($token === '') {
            return response()->view('auth.login', [
                'status' => 'Secure IVS callback is active and ready.'
            ], 200);
        }

        try {
            $result = $secureIvs->validateToken($token);

            if (empty($result['success'])) {
                $errorMsg = $result['message'] ?? 'Invalid or expired Shop Secure IVS verification token.';
                return redirect()->route('login')->withErrors(['email' => $errorMsg]);
            }

            $userData = $result['data']['user'] ?? $result['user'] ?? $result['data'] ?? [];
            $email = $userData['email'] ?? null;

            if (!$email) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Could not retrieve a verified email address from Shop Secure IVS.']);
            }

            $name = $userData['name'] ?? explode('@', $email)[0];

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(Str::random(32)),
                    'is_admin' => false,
                    'is_active' => true,
                ]
            );

            if (!$user->is_active) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Your account is deactivated. Please contact an administrator.']);
            }

            Auth::login($user, true);
            $request->session()->regenerate();

            return redirect()->intended(route('order-delivery.index'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->withErrors(['email' => 'Unable to complete Shop Secure IVS login: ' . $e->getMessage()]);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

}
