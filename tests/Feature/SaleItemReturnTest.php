<?php

namespace Tests\Feature;

use App\Http\Requests\StoreSaleItemRequest;
use App\Http\Requests\UpdateSaleItemRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleData;
use App\Models\SalesList;
use App\Services\SaleItemService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SaleItemReturnTest extends TestCase
{
    use DatabaseTransactions;

    protected Customer $customer;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

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

        $saleData = SaleData::where('orderid', $orderId)->firstOrFail();
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

        $saleData = SaleData::where('orderid', $orderId)->firstOrFail();
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
}

