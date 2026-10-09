<?php

namespace Tests\Feature;

use App\Http\Requests\StoreSaleItemRequest;
use App\Http\Requests\UpdateSaleItemRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleData;
use App\Models\SalesList;
use App\Services\SaleItemService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SaleItemReturnTest extends TestCase
{
    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('customers')) {
            Schema::create('customers', function (Blueprint $table): void {
                $table->id();
                $table->string('customer_id');
                $table->string('customer_name')->nullable();
                $table->integer('crm_exists')->default(0);
                $table->string('crm_link')->nullable();
                $table->string('crm_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('products')) {
            Schema::create('products', function (Blueprint $table): void {
                $table->id();
                $table->string('product_id');
                $table->string('product_name')->nullable();
                $table->decimal('price', 14, 2)->default(0);
                $table->decimal('retail', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sale_data')) {
            Schema::create('sale_data', function (Blueprint $table): void {
                $table->id();
                $table->date('date');
                $table->string('location');
                $table->string('type')->nullable();
                $table->string('payment')->nullable();
                $table->string('customer_id')->nullable();
                $table->string('customer_name')->nullable();
                $table->integer('crm_exists')->default(0);
                $table->string('crm_link')->nullable();
                $table->string('crm_id')->nullable();
                $table->string('orderid')->nullable();
                $table->string('original_orderid')->nullable();
                $table->string('product_id')->nullable();
                $table->string('product_name')->nullable();
                $table->integer('count')->nullable();
                $table->decimal('price', 14, 4)->nullable();
                $table->decimal('retail', 14, 2)->nullable();
                $table->unsignedBigInteger('sales_list_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sales_lists')) {
            Schema::create('sales_lists', function (Blueprint $table): void {
                $table->id();
                $table->date('date');
                $table->string('location');
                $table->string('type');
                $table->string('payment');
                $table->string('customerid');
                $table->string('orderid');
                $table->string('original_orderid')->nullable();
                $table->string('productid');
                $table->string('count');
                $table->timestamps();
            });
        }

        $randomCustId = (string) random_int(9000000, 9999999);
        $randomProdId = (string) random_int(8000000, 8999999);

        $this->customer = Customer::create([
            'customer_id' => $randomCustId,
            'customer_name' => 'Test Return Customer',
            'crm_exists' => 0,
        ]);

        $this->product = Product::create([
            'product_id' => $randomProdId,
            'product_name' => 'Test Paint 10L',
            'price' => 742.88,
            'retail' => 999.00,
        ]);
    }

    public function test_validation_allows_negative_count_for_returns(): void
    {
        $request = new StoreSaleItemRequest();
        $rules = $request->rules();

        $validPayload = [
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => 'RET_ORD_' . uniqid(),
            'productid' => [(string) $this->product->product_id],
            'count' => [-2], // Credit note / Return quantity
        ];

        $validator = Validator::make($validPayload, $rules);
        $this->assertTrue($validator->passes(), 'Negative count should pass validation for credit notes.');
    }

    public function test_validation_rejects_zero_count(): void
    {
        $request = new StoreSaleItemRequest();
        $rules = $request->rules();

        $invalidPayload = [
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => 'RET_ORD_' . uniqid(),
            'productid' => [(string) $this->product->product_id],
            'count' => [0],
        ];

        $validator = Validator::make($invalidPayload, $rules);
        $this->assertFalse($validator->passes(), 'Zero count must fail validation.');
        $this->assertTrue($validator->errors()->has('count.0'));
    }

    public function test_update_validation_allows_negative_count(): void
    {
        $request = new UpdateSaleItemRequest();
        $rules = $request->rules();

        $validPayload = [
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'productid' => (string) $this->product->product_id,
            'orderid' => 'UPD_ORD_' . uniqid(),
            'count' => -3,
        ];

        $validator = Validator::make($validPayload, $rules);
        $this->assertTrue($validator->passes(), 'Negative count on update should pass validation.');
    }

    public function test_store_and_calculate_returns_reduces_total_sales(): void
    {
        $service = app(SaleItemService::class);

        // 1. Enter a normal sale: +2 items @ 742.88
        $saleOrderId = 'SALE_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $saleOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [2],
        ]);

        // 2. Enter a return (credit note): -2 items @ 742.88
        $returnOrderId = 'RETURN_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $returnOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [-2],
        ]);

        // Check records in sales_lists and sale_data
        $this->assertDatabaseHas('sales_lists', [
            'orderid' => $returnOrderId,
            'productid' => (string) $this->product->product_id,
            'count' => '-2',
        ]);

        $this->assertDatabaseHas('sale_data', [
            'orderid' => $returnOrderId,
            'product_id' => (string) $this->product->product_id,
            'count' => -2,
        ]);

        // Verify aggregation mathematics: SUM(count) and SUM(count * price) for this customer
        $totals = DB::table('sale_data')
            ->where('customer_id', (string) $this->customer->customer_id)
            ->whereIn('orderid', [$saleOrderId, $returnOrderId])
            ->selectRaw('SUM(count) as total_qty, SUM(count * price) as net_sales')
            ->first();

        // 2 sold - 2 returned = 0 net quantity
        $this->assertEquals(0, (int) $totals->total_qty);
        // +1485.76 - 1485.76 = 0 net sales
        $this->assertEquals(0.00, round((float) $totals->net_sales, 2));
    }

    public function test_mistake_entry_can_be_edited_to_negative_count_and_updates_all_totals(): void
    {
        $service = app(SaleItemService::class);

        // 1. User accidentally entered a return as +2
        $orderId = 'MISTAKE_ORD_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $orderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [2], // Mistakenly entered as +2
        ]);

        $saleData = SaleData::query()->where('orderid', $orderId)->firstOrFail();
        $this->assertEquals(2, $saleData->count);

        // 2. User goes to Sold Registry -> clicks Edit -> changes count to -2
        $service->update($saleData, [
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $orderId,
            'productid' => (string) $this->product->product_id,
            'count' => -2, // Fixed to -2
        ]);

        // 3. Verify that both database tables are updated
        $this->assertDatabaseHas('sales_lists', [
            'orderid' => $orderId,
            'count' => '-2',
        ]);

        $this->assertDatabaseHas('sale_data', [
            'orderid' => $orderId,
            'count' => -2,
        ]);

        // 4. Verify that total calculations instantly reflect the -2 deduction
        $totals = DB::table('sale_data')
            ->where('orderid', $orderId)
            ->selectRaw('SUM(count) as total_qty, SUM(count * price) as net_sales')
            ->first();

        $this->assertEquals(-2, (int) $totals->total_qty);
        $this->assertEquals(-1485.76, round((float) $totals->net_sales, 2));
    }

    public function test_mistake_return_can_be_reversed_back_to_positive_sale_or_deleted(): void
    {
        $service = app(SaleItemService::class);

        // 1. User accidentally entered a sale as -2 (return)
        $orderId = 'WRONG_RET_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $orderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [-2], // Accidentally entered as Return
        ]);

        $saleData = SaleData::query()->where('orderid', $orderId)->firstOrFail();
        $this->assertEquals(-2, $saleData->count);

        // 2. Reverse via Edit: Change -2 to +2
        $service->update($saleData, [
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $orderId,
            'productid' => (string) $this->product->product_id,
            'count' => 2, // Reversing back to positive sale
        ]);

        $this->assertDatabaseHas('sale_data', [
            'orderid' => $orderId,
            'count' => 2,
        ]);

        $totals = DB::table('sale_data')
            ->where('orderid', $orderId)
            ->selectRaw('SUM(count) as total_qty, SUM(count * price) as net_sales')
            ->first();

        $this->assertEquals(2, (int) $totals->total_qty);
        $this->assertEquals(1485.76, round((float) $totals->net_sales, 2));

        // 3. Or Reverse via Delete: Remove the entry completely
        $service->delete($saleData->fresh());

        $this->assertDatabaseMissing('sale_data', ['orderid' => $orderId]);
        $this->assertDatabaseMissing('sales_lists', ['orderid' => $orderId]);
    }

    public function test_linked_return_records_separate_entry_and_snapshots_original_price(): void
    {
        $service = app(SaleItemService::class);

        // 1. Original sale: 5 products @ 742.88
        $origOrderId = 'ORIG_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [5],
        ]);

        // Change catalog product price in database to 999.00
        $this->product->update(['price' => 999.00]);

        // 2. Return 1 product linked to original sale
        $retOrderId = 'RET_' . uniqid();
        $service->create([
            'date' => '2026-09-01',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $retOrderId,
            'original_orderid' => $origOrderId,
            'is_return' => 1,
            'productid' => [(string) $this->product->product_id],
            'count' => [1], // user passes 1 in return mode, service enforces negative
        ]);

        // Both original sale and return exist as separate records for auditing (Rule 1, 2 & 7)
        $origRow = SaleData::where('orderid', $origOrderId)->first();
        $this->assertNotNull($origRow);
        $this->assertEquals(5, $origRow->count);

        $retRow = SaleData::where('orderid', $retOrderId)->first();
        $this->assertNotNull($retRow);
        $this->assertEquals(-1, $retRow->count);
        $this->assertEquals($origOrderId, $retRow->original_orderid);
        // Unit price matches original sale (742.88) not catalog price (999.00)
        $this->assertEquals(742.88, (float) $retRow->price);

        // Combined Net sales quantity = 4, Net revenue = 4 * 742.88 = 2971.52
        $net = DB::table('sale_data')
            ->whereIn('orderid', [$origOrderId, $retOrderId])
            ->selectRaw('SUM(count) as net_count, SUM(count * price) as net_revenue')
            ->first();
        $this->assertEquals(4, (int) $net->net_count);
        $this->assertEquals(2971.52, round((float) $net->net_revenue, 2));
    }

    public function test_return_can_use_the_same_order_id_as_the_original_sale(): void
    {
        $service = app(SaleItemService::class);
        $orderId = 'SAME_' . uniqid();

        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $orderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [5],
        ]);

        $response = $this->post('/home', [
            'date' => '2026-09-01',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $orderId,
            'original_orderid' => $orderId,
            'is_return' => '1',
            'productid' => [(string) $this->product->product_id],
            'count' => [1],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sale_data', [
            'orderid' => $orderId,
            'original_orderid' => $orderId,
            'product_id' => (string) $this->product->product_id,
            'count' => -1,
        ]);
        $this->assertSame(4, (int) SaleData::where('orderid', $orderId)->sum('count'));
    }

    public function test_validation_prevents_returning_more_than_original_sold_quantity(): void
    {
        $service = app(SaleItemService::class);

        $origOrderId = 'ORIG_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [5],
        ]);

        $request = new StoreSaleItemRequest();
        $payload = [
            'date' => '2026-09-01',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => 'RET_' . uniqid(),
            'original_orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [6], // Attempting to return 6 when only 5 were bought
        ];

        $validator = Validator::make($payload, $request->rules(), $request->messages());
        foreach ($request->after() as $afterHook) {
            $validator->after($afterHook);
        }

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('count.0'));
        $this->assertStringContainsString('Cannot return 6 units', $validator->errors()->first('count.0'));
    }

    public function test_validation_prevents_double_returning_same_product(): void
    {
        $service = app(SaleItemService::class);

        // Sold 5
        $origOrderId = 'ORIG_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [5],
        ]);

        // First return: returned 3 (leaving 2)
        $service->create([
            'date' => '2026-09-01',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => 'RET_1_' . uniqid(),
            'original_orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [-3],
        ]);

        // Second return: trying to return 3 again (exceeds 2 remaining)
        $request = new StoreSaleItemRequest();
        $payload = [
            'date' => '2026-09-02',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => 'RET_2_' . uniqid(),
            'original_orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [-3],
        ];

        $validator = Validator::make($payload, $request->rules(), $request->messages());
        foreach ($request->after() as $afterHook) {
            $validator->after($afterHook);
        }

        $this->assertFalse($validator->passes());
        $this->assertTrue($validator->errors()->has('count.0'));
        $this->assertStringContainsString('Only 2 remaining', $validator->errors()->first('count.0'));
    }

    public function test_get_order_details_endpoint_returns_accurate_remaining_counts(): void
    {
        $service = app(SaleItemService::class);

        $origOrderId = 'ORD_DETAIL_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [5],
        ]);

        // Return 2
        $service->create([
            'date' => '2026-09-01',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => 'RET_DETAIL_' . uniqid(),
            'original_orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [-2],
        ]);

        $response = $this->getJson(route('get_order_details', ['orderid' => $origOrderId]));
        $response->assertOk();
        $response->assertJson([
            'orderid' => $origOrderId,
            'customer_id' => (string) $this->customer->customer_id,
        ]);

        $products = $response->json('products');
        $this->assertCount(1, $products);
        $this->assertEquals(5, $products[0]['original_count']);
        $this->assertEquals(2, $products[0]['already_returned_count']);
        $this->assertEquals(3, $products[0]['available_return_count']);
    }

    public function test_customer_report_deducts_returns_automatically_and_prevents_double_counting(): void
    {
        $service = app(SaleItemService::class);

        $origOrderId = 'CR_ORIG_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [5],
        ]);

        $retOrderId = 'CR_RET_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $retOrderId,
            'original_orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [-1],
        ]);

        $response = $this->getJson('/CustomerReport/details?' . http_build_query([
            'customer_id' => $this->customer->customer_id,
            'location' => 'MAJORSTUEN',
            'days' => 0,
        ]));

        $response->assertOk();
        $data = $response->json();

        // Find the original order row in the customer report drilldown
        $origRow = collect($data)->firstWhere('orderid', $origOrderId);
        $this->assertNotNull($origRow);
        // It has returns!
        $this->assertTrue($origRow['has_returns']);
        // Gross was 5, returned is 1, net total_products_sold is 4
        $this->assertEquals(5, $origRow['gross_sold']);
        $this->assertEquals(1, $origRow['returned_count']);
        $this->assertEquals(4, $origRow['total_products_sold']);
        // Net sales: 4 * 742.88 = 2971.52
        $this->assertEquals(2971.52, round($origRow['total_sales'], 2));

        // Find the linked return row
        $retRow = collect($data)->firstWhere('orderid', $retOrderId);
        $this->assertNotNull($retRow);
        $this->assertTrue($retRow['is_return']);
        $this->assertTrue($retRow['is_linked_return']);
        // The linked return is already deducted from the parent, so the daily
        // product total is the net quantity and is not deducted a second time.
        $this->assertEquals(4, $origRow['daily_total_orders']);
    }

    public function test_customer_final_details_provides_gross_net_and_returns_breakdown(): void
    {
        $service = app(SaleItemService::class);

        $origOrderId = 'FD_ORIG_' . uniqid();
        $service->create([
            'date' => '2026-08-31',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [5],
        ]);

        $retOrderId = 'FD_RET_' . uniqid();
        $service->create([
            'date' => '2026-09-01',
            'location' => 'MAJORSTUEN',
            'type' => 'MalProff MPP',
            'payment' => 'Invoice',
            'customerid' => (string) $this->customer->customer_id,
            'orderid' => $retOrderId,
            'original_orderid' => $origOrderId,
            'productid' => [(string) $this->product->product_id],
            'count' => [-1],
        ]);

        $response = $this->getJson('/customer/finaldetails?' . http_build_query([
            'orderid' => $origOrderId,
            'customerId' => $this->customer->customer_id,
        ]));

        $response->assertOk();
        $items = $response->json();
        $this->assertCount(1, $items);
        $item = $items[0];

        $this->assertEquals(5, $item['gross_count']);
        $this->assertEquals(1, $item['returned_count']);
        $this->assertEquals(4, $item['count']); // net count
        $this->assertEquals(3714.40, round($item['gross_total_price'], 2)); // 5 * 742.88
        $this->assertEquals(742.88, round($item['returned_total_price'], 2)); // 1 * 742.88
        $this->assertEquals(2971.52, round($item['total_price'], 2)); // 4 * 742.88 net
        $this->assertCount(1, $item['returns']);
        $this->assertEquals($retOrderId, $item['returns'][0]['return_orderid']);
    }
}
