<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleData;
use App\Models\SalesList;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SaleItemService
{
    public function create(array $data): void
    {
        DB::transaction(function () use ($data): void {
            $customer = Customer::where('customer_id', $data['customerid'])->firstOrFail();
            $products = Product::whereIn('product_id', $data['productid'])
                ->get()
                ->keyBy('product_id');

            foreach ($data['productid'] as $key => $productId) {
                $product = $products->get($productId);
                $salesList = SalesList::create($this->salesListPayload($data, $productId, $data['count'][$key]));

                SaleData::create($this->saleDataPayload(
                    $data,
                    $customer,
                    $product,
                    $data['count'][$key],
                    $salesList->id,
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

            $saleItem->update($this->salesListPayload($data, $data['productid'], $data['count']));
            $saleData->update($this->saleDataPayload($data, $customer, $product, $data['count'], $saleItem->id));
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

    private function salesListPayload(array $data, string $productId, int $count): array
    {
        return [
            'date' => $data['date'],
            'location' => $data['location'],
            'type' => $data['type'],
            'payment' => $data['payment'],
            'customerid' => $data['customerid'],
            'orderid' => $data['orderid'],
            'productid' => $productId,
            'count' => $count,
        ];
    }

    private function saleDataPayload(array $data, Customer $customer, Product $product, int $count, int $salesListId): array
    {
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
            'product_id' => $product->product_id,
            'product_name' => $product->product_name,
            'price' => $this->snapshotUnitPrice($product),
            'retail' => $product->retail,
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
