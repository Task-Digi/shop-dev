<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SecureIvsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function redirectToSecureIvs(Request $request, SecureIvsService $secureIvs)
    {
        if (Auth::check()) {
            return redirect()->intended(route('order-delivery.index'));
        }

        $state = Str::random(64);
        $request->session()->put('secureivs.login_state', $state);
        $callbackUrl = route('login.secureivs.callback');
        $redirectUrl = $secureIvs->getRedirectUrl($callbackUrl, $state);

        return redirect()->away($redirectUrl);
    }

    public function handleSecureIvsCallback(Request $request, SecureIvsService $secureIvs)
    {
        $token = (string) $request->query('token', '');

        if ($token === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Secure IVS did not return a verification token.']);
        }

        try {
            $result = $secureIvs->validateToken($token);

            if (! $result['valid']) {
                $errorMsg = $result['message'] ?? 'Invalid or expired Shop Secure IVS verification token.';

                return redirect()->route('login')->withErrors(['email' => $errorMsg]);
            }

            $userData = $result['user'] ?? [];
            $email = Str::lower(trim((string) ($userData['email'] ?? '')));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Could not retrieve a verified email address from Shop Secure IVS.']);
            }

            $expectedState = (string) $request->session()->pull('secureivs.login_state', '');
            $context = $secureIvs->unpackReturnCode($userData['return_code'] ?? null);
            $returnedState = (string) ($context['state'] ?? '');

            if ($expectedState === '' || $returnedState === '' || ! hash_equals($expectedState, $returnedState)) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Secure IVS login session could not be verified. Please try again.']);
            }

            $subscriptionStatus = (string) ($userData['subscription_status'] ?? '');
            if (! in_array($subscriptionStatus, ['A', 'V'], true)) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Your Secure IVS account is not active.']);
            }

            // SHOP access is allow-listed: an administrator must create the user first.
            $user = User::where('email', $email)->first();

            if (! $user) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Your email is not registered in the SHOP system. Please contact your administrator.']);
            }

            if (! $user->is_active) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Your account is deactivated. Please contact an administrator.']);
            }

            Auth::login($user, true);
            $request->session()->regenerate();
            $request->session()->put('secureivs.token', $token);
            $user->forceFill(['last_login_at' => now()])->save();

            return redirect()->intended(route('order-delivery.index'));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->withErrors(['email' => 'Unable to complete Shop Secure IVS login. Please try again.']);
        }
    }

    public function logout(Request $request, SecureIvsService $secureIvs)
    {
        $token = (string) $request->session()->get('secureivs.token', '');

        try {
            $secureIvs->logout($token);
        } catch (\Throwable $e) {
            report($e);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
