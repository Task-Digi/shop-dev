<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderDeliveryAccessTest extends TestCase
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

    public function test_guest_is_redirected_to_login_from_orders(): void
    {
        $this->get('/order-delivery')->assertRedirect('/login');
    }

    public function test_user_without_orders_service_receives_forbidden(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/order-delivery')->assertForbidden();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->user(['is_active' => false]);

        $this->post('/login', ['email' => 'user@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_active_user_with_orders_permission_can_pass_service_check(): void
    {
        $user = $this->user();
        $orders = Service::create(['name' => 'Orders', 'slug' => 'orders']);
        $user->services()->attach($orders);

        $this->assertTrue($user->canAccessService('orders'));
    }

    public function test_admin_can_access_every_service(): void
    {
        $user = $this->user(['is_admin' => true]);

        $this->assertTrue($user->canAccessService('orders'));
    }

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'is_admin' => false,
            'is_active' => true,
        ], $overrides));
    }
}
