<?php

namespace Tests\Feature;

use App\Http\Controllers\OrderDeliveryController;
use App\Models\OrderItem;
use App\Models\OrderRecord;
use App\Models\OrderScan;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderDeliveryZeroDeliveredTargetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('service_user');
        Schema::dropIfExists('services');
        Schema::dropIfExists('users');
        Schema::dropIfExists('order_scans');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_records');
        Schema::dropIfExists('products');

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

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_id')->unique();
            $table->string('product_name')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('retail', 10, 2)->default(0);
            $table->string('ean_code')->nullable();
            $table->timestamps();
        });

        Schema::create('order_records', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id')->unique();
            $table->date('order_date')->nullable();
            $table->date('planned_delivery')->nullable();
            $table->dateTime('delivery_handling_date')->nullable();
            $table->string('status')->default('Started');
            $table->string('staff')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id');
            $table->string('order_id2')->nullable();
            $table->date('order_date')->nullable();
            $table->string('ordered_by')->nullable();
            $table->date('planned_delivery')->nullable();
            $table->string('status')->nullable();
            $table->string('your_reference')->nullable();
            $table->string('sku')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('packaging_quantity')->default(1);
            $table->string('packaging_unit')->nullable();
            $table->integer('ordered_quantity')->default(0);
            $table->integer('delivered')->default(0);
            $table->integer('quantity')->default(0);
            $table->decimal('price', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('order_scans', function (Blueprint $table): void {
            $table->id();
            $table->string('order_id');
            $table->dateTime('scan_date_time')->nullable();
            $table->string('ean_code');
            $table->integer('units')->default(1);
            $table->boolean('deactivated')->default(false);
            $table->timestamps();
        });
    }

    public function test_delivered_zero_uses_zero_as_target_and_does_not_block_closing_order(): void
    {
        $orderId = 'TEST-ORDER-ZERO';

        OrderRecord::create([
            'order_id' => $orderId,
            'status' => 'Started',
        ]);

        // Item 1: Delivered = 2, Ordered = 2 (standard delivered item)
        OrderItem::create([
            'order_id' => $orderId,
            'sku' => 'SKU-DELIVERED',
            'item_name' => 'Delivered Product',
            'ordered_quantity' => 2,
            'quantity' => 2,
            'delivered' => 2,
            'price' => 100.00,
        ]);

        // Item 2: Delivered = 0, Ordered = 5 (undelivered / out of stock item mentioned by Torkel)
        OrderItem::create([
            'order_id' => $orderId,
            'sku' => 'SKU-UNDELIVERED',
            'item_name' => 'Undelivered Product',
            'ordered_quantity' => 5,
            'quantity' => 5,
            'delivered' => 0,
            'price' => 50.00,
        ]);

        Product::create([
            'product_id' => 'SKU-DELIVERED',
            'ean_code' => '700000000001',
            'product_name' => 'Delivered Product',
            'price' => 100.00,
        ]);

        Product::create([
            'product_id' => 'SKU-UNDELIVERED',
            'ean_code' => '700000000002',
            'product_name' => 'Undelivered Product',
            'price' => 50.00,
        ]);

        // Scan only the delivered items (2 scans for SKU-DELIVERED)
        OrderScan::create([
            'order_id' => $orderId,
            'ean_code' => '700000000001',
            'units' => 2,
            'deactivated' => false,
        ]);

        $controller = app(OrderDeliveryController::class);

        // Check product state for the undelivered item
        $state = $controller->getProductState($orderId, '700000000002');
        $this->assertEquals(0, $state['delivery_target'], 'Target for delivered=0 item should be 0');
        $this->assertEquals(5, $state['ordered'], 'Ordered count should still be 5 for note');
        $this->assertEquals(0, $state['remaining'], 'Rest should be 0');
        $this->assertEquals('COMPLETE', $state['status'], 'Status should be COMPLETE when 0 scanned and 0 target');

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
            'password' => Hash::make('secret'),
            'is_admin' => true,
        ]);

        $ordersService = Service::create(['name' => 'Orders', 'slug' => 'orders']);
        $admin->services()->attach($ordersService);

        // Check show page renders DELIVERED header and (Ord: 5) note
        $showResponse = $this->actingAs($admin)->get(route('order-delivery.show', $orderId));
        $showResponse->assertOk();
        $showResponse->assertSee('DELIVERED');
        $showResponse->assertSee('(Ord: 5)');

        $response = $this->actingAs($admin)->post('/order-delivery/close', [
            'order_id' => $orderId,
            'staff' => 'Tester',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_records', [
            'order_id' => $orderId,
            'status' => 'Completed',
        ]);

        // Reopen order without password
        $reopenResponse = $this->actingAs($admin)->post('/order-delivery/reopen', [
            'order_id' => $orderId,
        ]);

        $reopenResponse->assertOk()->assertJson([
            'success' => true,
            'status' => 'Started',
        ]);
        $this->assertDatabaseHas('order_records', [
            'order_id' => $orderId,
            'status' => 'Started',
        ]);
    }

    public function test_mobile_quantity_actions_consolidate_duplicate_product_scans(): void
    {
        config(['broadcasting.default' => 'null']);

        $orderId = 'MOBILE-SYNC-ORDER';
        OrderRecord::create(['order_id' => $orderId, 'status' => 'Started']);
        Product::create([
            'product_id' => 'SKU-MOBILE',
            'ean_code' => '7029350262046',
            'product_name' => 'Mobile Product',
        ]);
        OrderItem::create([
            'order_id' => $orderId,
            'sku' => 'SKU-MOBILE',
            'item_name' => 'Mobile Product',
            'ordered_quantity' => 3,
            'quantity' => 3,
            'delivered' => 3,
        ]);

        $first = OrderScan::create([
            'order_id' => $orderId,
            'ean_code' => '7029350262046',
            'units' => 1,
            'deactivated' => false,
        ]);
        $duplicate = OrderScan::create([
            'order_id' => $orderId,
            'ean_code' => 'SKU-MOBILE',
            'units' => 2,
            'deactivated' => false,
        ]);

        $controller = app(OrderDeliveryController::class);
        $minusResponse = $controller->updateUnits(Request::create('/order-delivery/update-units', 'POST', [
            'scan_id' => $first->id,
            'change' => -1,
        ]));
        $minusData = $minusResponse->getData(true);

        $this->assertTrue($minusData['success']);
        $this->assertSame(2, $minusData['units']);
        $this->assertSame(2, $minusData['scanned']);
        $this->assertContains($duplicate->id, $minusData['removed_scan_ids']);
        $this->assertDatabaseHas('order_scans', ['id' => $first->id, 'units' => 2, 'deactivated' => false]);
        $this->assertDatabaseHas('order_scans', ['id' => $duplicate->id, 'deactivated' => true]);

        $okResponse = $controller->updateUnitsToMatchOrder(Request::create('/order-delivery/match-order', 'POST', [
            'scan_id' => $first->id,
        ]));
        $okData = $okResponse->getData(true);

        $this->assertTrue($okData['success']);
        $this->assertSame(3, $okData['units']);
        $this->assertSame(3, $okData['scanned']);

        $scanResponse = $controller->scan(Request::create('/order-delivery/scan', 'POST', [
            'order_id' => $orderId,
            'ean_code' => '7029350262046',
        ]));
        $scanData = $scanResponse->getData(true);

        $this->assertSame($first->id, $scanData['scan_id']);
        $this->assertSame(4, $scanData['units']);
        $this->assertSame(1, OrderScan::where('order_id', $orderId)->where('deactivated', false)->count());
    }
}
