<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SecureIvsLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('service_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id');
            $table->foreignId('user_id');
            $table->timestamps();
        });
    }

    public function test_login_directly_redirects_to_secure_ivs(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(302);
        $targetUrl = $response->headers->get('Location');

        $this->assertStringContainsString('https://customer-api.secureivs.eu', $targetUrl);
        $this->assertStringContainsString('client_id=9b3tvZGZStRrcTsfTZifsjgkaUTER3aOKaXjshkO', $targetUrl);
        $this->assertStringContainsString('login_method=email', $targetUrl);
    }

    public function test_login_page_renders_error_card_when_errors_exist(): void
    {
        $bag = new \Illuminate\Support\MessageBag(['email' => 'Sample error']);
        $errorBag = new \Illuminate\Support\ViewErrorBag();
        $errorBag->put('default', $bag);

        $response = $this->withSession(['errors' => $errorBag])->get('/login');

        $response->assertStatus(200);
        $response->assertSee('Login with Shop Secure IVS');
        $response->assertSee(route('login.secureivs'));
        $response->assertDontSee('name="password"', false);
    }

    public function test_redirect_to_secure_ivs_routes_to_provider_with_client_id_and_callback(): void
    {
        $response = $this->get(route('login.secureivs'));

        $response->assertStatus(302);
        $targetUrl = $response->headers->get('Location');

        $this->assertStringContainsString('https://customer-api.secureivs.eu', $targetUrl);
        $this->assertStringContainsString('client_id=9b3tvZGZStRrcTsfTZifsjgkaUTER3aOKaXjshkO', $targetUrl);
        $this->assertStringContainsString('login_method=email', $targetUrl);
        $expectedRedirect = config('services.secureivs.redirect_uri') ?: route('login.secureivs.callback');
        $this->assertStringContainsString(urlencode($expectedRedirect), $targetUrl);
    }

    public function test_callback_without_token_renders_callback_ready_view(): void
    {
        $response = $this->get(route('login.secureivs.callback'));

        $response->assertStatus(200);
        $response->assertSee('Login with Shop Secure IVS');
    }

    public function test_callback_with_valid_token_logs_in_existing_user(): void
    {
        $existing = User::create([
            'name' => 'Theepan',
            'email' => 'theepan@digifront.biz',
            'password' => bcrypt('secret123'),
            'is_admin' => true,
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.secureivs.eu/api/v2/auth/token/validate' => Http::response([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'user' => [
                        'email' => 'theepan@digifront.biz',
                        'name' => 'Theepan Digifront',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get(route('login.secureivs.callback', ['token' => 'sample_valid_token']));

        $response->assertRedirect(route('order-delivery.index'));
        $this->assertAuthenticatedAs($existing);
    }

    public function test_callback_with_valid_token_auto_creates_and_logs_in_new_user(): void
    {
        Http::fake([
            'https://api.secureivs.eu/api/v2/auth/token/validate' => Http::response([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'user' => [
                        'email' => 'newuser@example.com',
                        'name' => 'New User',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get(route('login.secureivs.callback', ['token' => 'token_for_new_user']));

        $response->assertRedirect(route('order-delivery.index'));
        $this->assertAuthenticated();

        $newUser = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($newUser);
        $this->assertSame('New User', $newUser->name);
        $this->assertTrue($newUser->is_active);
    }

    public function test_callback_with_inactive_user_blocks_login(): void
    {
        User::create([
            'name' => 'Disabled User',
            'email' => 'disabled@example.com',
            'password' => bcrypt('secret123'),
            'is_admin' => false,
            'is_active' => false,
        ]);

        Http::fake([
            'https://api.secureivs.eu/api/v2/auth/token/validate' => Http::response([
                'success' => true,
                'code' => 'SUCCESS',
                'data' => [
                    'user' => [
                        'email' => 'disabled@example.com',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->get(route('login.secureivs.callback', ['token' => 'token_disabled']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_callback_with_invalid_token_shows_error(): void
    {
        Http::fake([
            'https://api.secureivs.eu/api/v2/auth/token/validate' => Http::response([
                'success' => false,
                'code' => 'TOKEN_EXPIRED',
                'message' => 'The token has expired.',
            ], 401),
        ]);

        $response = $this->get(route('login.secureivs.callback', ['token' => 'expired_token']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
