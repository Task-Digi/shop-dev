<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleData;
use App\Models\SalesList;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Illuminate\Validation\ValidationException;

class SaleItemService
{
    public function create(array $data): void
    {
        DB::transaction(function () use ($data): void {
            if (Schema::hasTable('sales_reporting_days') && DB::table('sales_reporting_days')
                ->where('date', $data['date'])
                ->where('location', $data['location'])
                ->where('sales_amount', 0)
                ->exists()) {
                throw ValidationException::withMessages([
                    'date' => 'A zero-sales record already exists for this location and date. Remove it before entering actual sales.',
                ]);
            }

            $customer = Customer::where('customer_id', $data['customerid'])->firstOrFail();
            $products = Product::whereIn('product_id', $data['productid'])
                ->get()
                ->keyBy('product_id');

            $originalOrderId = !empty($data['original_orderid']) ? trim((string) $data['original_orderid']) : null;
            $isReturnEntry = !empty($data['is_return']) || $originalOrderId !== null;

            // When linked to an original order, look up the original sale rows to snapshot original unit price & retail
            $origSaleItems = $originalOrderId
                ? SaleData::where('orderid', $originalOrderId)->get()->keyBy('product_id')
                : collect();

            foreach ($data['productid'] as $key => $productId) {
                $product = $products->get($productId);
                $rawCount = (int) $data['count'][$key];

                // If marked as return or linked to an original sale, ensure count is negative
                $count = ($isReturnEntry && $rawCount > 0) ? -$rawCount : $rawCount;

                $origItem = $origSaleItems->get($productId);

                $salesList = SalesList::create($this->salesListPayload($data, $productId, $count, $originalOrderId));

                SaleData::create($this->saleDataPayload(
                    $data,
                    $customer,
                    $product,
                    $count,
                    $salesList->id,
                    $originalOrderId,
                    $origItem?->price,
                    $origItem?->retail
                ));
            }
        });
    }

    public function update(SaleData $saleData, array $data): void
    {
        DB::transaction(function () use ($saleData, $data): void {
            $saleItem = $saleData->sales_list_id
                ? SalesList::find($saleData->sales_list_id)
                : SalesList::where('orderid', $saleData->orderid)
                    ->where('productid', $saleData->product_id)
                    ->first();

            if (! $saleItem) {
                throw new RuntimeException('Linked sale item not found.');
            }

            $customer = Customer::where('customer_id', $data['customerid'])->firstOrFail();
            $product = Product::where('product_id', $data['productid'])->firstOrFail();

            $originalOrderId = !empty($data['original_orderid']) ? trim((string) $data['original_orderid']) : null;
            $rawCount = (int) $data['count'];
            $isReturn = !empty($data['is_return']) || $originalOrderId !== null;
            $count = ($isReturn && $rawCount > 0) ? -$rawCount : $rawCount;

            $origItem = $originalOrderId
                ? SaleData::where('orderid', $originalOrderId)->where('product_id', $data['productid'])->first()
                : null;

            $saleItem->update($this->salesListPayload($data, $data['productid'], $count, $originalOrderId));
            $saleData->update($this->saleDataPayload(
                $data,
                $customer,
                $product,
                $count,
                $saleItem->id,
                $originalOrderId,
                $origItem?->price,
                $origItem?->retail
            ));
        });
    }

    public function delete(SaleData $saleData): void
    {
        DB::transaction(function () use ($saleData): void {
            $saleItem = $saleData->sales_list_id
                ? SalesList::find($saleData->sales_list_id)
                : SalesList::where('orderid', $saleData->orderid)
                    ->where('productid', $saleData->product_id)
                    ->first();

            $saleItem?->delete();
            $saleData->delete();
        });
    }

    private function salesListPayload(array $data, string $productId, int $count, ?string $originalOrderId = null): array
    {
        return [
            'date' => $data['date'],
            'location' => $data['location'],
            'type' => $data['type'],
            'payment' => $data['payment'],
            'customerid' => $data['customerid'],
            'orderid' => $data['orderid'],
            'original_orderid' => $originalOrderId,
            'productid' => $productId,
            'count' => $count,
        ];
    }

    private function saleDataPayload(
        array $data,
        Customer $customer,
        Product $product,
        int $count,
        int $salesListId,
        ?string $originalOrderId = null,
        ?float $overridePrice = null,
        ?float $overrideRetail = null
    ): array {
        $price = $overridePrice !== null ? round((float) $overridePrice, 4) : $this->snapshotUnitPrice($product);
        $retail = $overrideRetail !== null ? (float) $overrideRetail : (float) $product->retail;

        return [
            'date' => $data['date'],
            'location' => $data['location'],
            'type' => $data['type'],
            'payment' => $data['payment'],
            'customer_id' => $data['customerid'],
            'customer_name' => $customer->customer_name,
            'crm_exists' => $customer->crm_exists,
            'crm_link' => $customer->crm_link,
            'crm_id' => $customer->crm_id,
            'orderid' => $data['orderid'],
            'original_orderid' => $originalOrderId,
            'product_id' => $product->product_id,
            'product_name' => $product->product_name,
            'price' => $price,
            'retail' => $retail,
            'count' => $count,
            'sales_list_id' => $salesListId,
        ];
    }

    private function snapshotUnitPrice(Product $product): ?float
    {
        return ($product->price === null || $product->price === '')
            ? null
            : round((float) $product->price, 4);
    }
}
