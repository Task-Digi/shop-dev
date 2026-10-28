<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecureIvsLoginTest extends TestCase
{
    private const VALIDATE_URL = 'https://api.secureivs.eu/api/v2/auth/token/validate';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.secureivs', [
            'client_id' => 'test-client-id',
            'api_key' => 'test-api-key',
            'auth_url' => 'https://customer-api.secureivs.eu',
            'base_url' => 'https://api.secureivs.eu',
            'redirect_uri' => 'https://shop.example.com/login/secureivs/callback',
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function test_login_redirect_contains_public_client_configuration_and_csrf_context(): void
    {
        $response = $this->get(route('login.secureivs'));

        $response->assertRedirectContains('https://customer-api.secureivs.eu/');
        $target = (string) $response->headers->get('Location');
        $this->assertStringContainsString('client_id=test-client-id', $target);
        $this->assertStringContainsString('login_method=email', $target);
        $this->assertStringContainsString('return_code=', $target);
        $this->assertNotEmpty(session('secureivs.login_state'));
    }

    public function test_callback_logs_in_only_a_pre_authorized_active_user(): void
    {
        $user = $this->user();
        $state = $this->beginLogin();
        $this->fakeValidation($this->profile($state));

        $response = $this->get(route('login.secureivs.callback', ['token' => 'valid-token']));

        $response->assertRedirect(route('order-delivery.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('valid-token', session('secureivs.token'));
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_verified_but_unlisted_user_is_denied_and_not_created(): void
    {
        $state = $this->beginLogin();
        $this->fakeValidation($this->profile($state, 'unknown@example.com'));

        $response = $this->get(route('login.secureivs.callback', ['token' => 'valid-token']));

        $response->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
    }

    public function test_inactive_pre_authorized_user_is_denied(): void
    {
        $this->user(['is_active' => false]);
        $state = $this->beginLogin();
        $this->fakeValidation($this->profile($state));

        $this->get(route('login.secureivs.callback', ['token' => 'valid-token']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_rejects_a_mismatched_login_state(): void
    {
        $this->user();
        $this->beginLogin();
        $this->fakeValidation($this->profile('different-state'));

        $this->get(route('login.secureivs.callback', ['token' => 'valid-token']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_callback_rejects_an_inactive_secure_ivs_subscription(): void
    {
        $this->user();
        $state = $this->beginLogin();
        $profile = $this->profile($state);
        $profile['subscription_status'] = 'I';
        $this->fakeValidation($profile);

        $this->get(route('login.secureivs.callback', ['token' => 'valid-token']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_invalid_token_and_missing_token_are_rejected(): void
    {
        $this->beginLogin();
        Http::fake([
            self::VALIDATE_URL => Http::response([
                'success' => false,
                'code' => 'TOKEN_EXPIRED',
                'message' => 'The token has expired.',
                'requestId' => 'request-1',
            ], 401),
        ]);

        $this->get(route('login.secureivs.callback', ['token' => 'expired']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->get(route('login.secureivs.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_logout_revokes_secure_ivs_token_and_destroys_local_session(): void
    {
        $user = $this->user();
        Http::fake([
            'https://api.secureivs.eu/api/v2/auth/token/logout' => Http::response(['success' => true], 200),
        ]);

        $this->actingAs($user)->withSession(['secureivs.token' => 'valid-token'])
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.secureivs.eu/api/v2/auth/token/logout'
            && $request['token'] === 'valid-token');
    }

    private function beginLogin(): string
    {
        $this->get(route('login.secureivs'))->assertRedirect();

        return (string) session('secureivs.login_state');
    }

    /** @return array<string, string> */
    private function profile(string $state, string $email = 'theepan@digifront.biz'): array
    {
        return [
            'email' => $email,
            'name' => 'Verified User',
            'subscription_status' => 'A',
            'return_code' => base64_encode((string) json_encode(['state' => $state])),
        ];
    }

    /** @param array<string, string> $profile */
    private function fakeValidation(array $profile): void
    {
        Http::fake([
            self::VALIDATE_URL => Http::response([
                'success' => true,
                'code' => 'SUCCESS',
                'requestId' => 'request-1',
                'data' => ['user' => $profile],
            ], 200),
        ]);
    }

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Theepan',
            'email' => 'theepan@digifront.biz',
            'password' => bcrypt('unused-password'),
            'is_admin' => true,
            'is_active' => true,
        ], $overrides));
    }
}
