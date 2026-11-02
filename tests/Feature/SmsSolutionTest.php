<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmsSolutionTest extends TestCase
{
    private const ENDPOINT = 'https://smsgateway.example.test/smsgateway.php';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.sms_gateway', [
            'client_id' => '5',
            'api_key' => 'server-only-test-key',
            'endpoint' => self::ENDPOINT,
            'sender' => 'Fabconcept',
        ]);

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
    }

    public function test_sms_page_requires_login(): void
    {
        $this->get(route('sms.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_sms_page(): void
    {
        $this->actingAs($this->user())
            ->get(route('sms.index'))
            ->assertOk()
            ->assertSee('SMS Solution')
            ->assertDontSee('server-only-test-key');
    }

    public function test_sms_is_sent_server_side_with_normalized_destination(): void
    {
        Http::fake([
            self::ENDPOINT.'*' => Http::response([
                'status' => 'success',
                'transaction_id' => 'tx-123',
            ]),
        ]);

        $this->actingAs($this->user())
            ->post(route('sms.send'), [
                'destination' => '+47 912 34 567',
                'message' => 'Your order has been shipped',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('sms_result', fn (array $result) => $result['transaction_id'] === 'tx-123');

        Http::assertSent(fn ($request) => str_starts_with($request->url(), self::ENDPOINT.'?')
            && $request['key'] === 'server-only-test-key'
            && $request['avs'] === 'Fabconcept'
            && $request['dest'] === '4791234567'
            && $request['msg'] === 'Your order has been shipped');
    }

    public function test_invalid_destination_is_rejected_without_calling_gateway(): void
    {
        Http::fake();

        $this->actingAs($this->user())
            ->post(route('sms.send'), [
                'destination' => '123',
                'message' => 'Test',
            ])
            ->assertSessionHasErrors('destination');

        Http::assertNothingSent();
    }

    public function test_rejected_gateway_credentials_are_reported_as_an_error(): void
    {
        Http::fake([
            self::ENDPOINT.'*' => Http::response('Client not found.'),
        ]);

        $this->actingAs($this->user())
            ->post(route('sms.send'), [
                'destination' => '4791234567',
                'message' => 'Test',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sms');
    }

    private function user(): User
    {
        return User::create([
            'name' => 'SMS User',
            'email' => 'sms-user@example.com',
            'password' => bcrypt('unused-password'),
            'is_active' => true,
        ]);
    }
}
