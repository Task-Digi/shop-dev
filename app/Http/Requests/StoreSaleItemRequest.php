<?php

namespace App\Http\Requests;

use App\Models\SaleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $orderIdRules = ['required', 'string', 'max:255'];
        if (!$this->boolean('is_return') && !$this->filled('original_orderid')) {
            $orderIdRules[] = 'unique:sales_lists,orderid';
        }

        return [
            'date' => ['required', 'date'],
            'location' => ['required', Rule::in(['ALNABRU', 'MAJORSTUEN'])],
            'type' => ['required', Rule::in(['MalProff MPP', 'FARGERIKE'])],
            'payment' => ['required', Rule::in(['Invoice', 'Cash/Card'])],
            'customerid' => ['required', 'string', 'max:255', 'exists:customers,customer_id'],
            'orderid' => $orderIdRules,
            'original_orderid' => ['nullable', 'string', 'max:255'],
            'is_return' => ['nullable'],
            'productid' => ['required', 'array', 'min:1', 'max:100'],
            'productid.*' => ['required', 'string', 'max:255', 'distinct', 'exists:products,product_id'],
            'count' => ['required', 'array', 'min:1', 'max:100'],
            'count.*' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customerid.exists' => 'The selected Customer ID was not found.',
            'orderid.unique' => 'Order ID already exists. Please enter a different Order ID.',
            'productid.*.exists' => 'One or more Product IDs were not found.',
            'productid.*.distinct' => 'Each Product ID can only be entered once per order.',
            'count.*.not_in' => 'Quantity cannot be 0. Enter a positive number for sales or a negative number for returns.',
            'count.*.min' => 'Quantity must be at least -1,000,000.',
            'count.*.max' => 'Quantity cannot exceed 1,000,000.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $validator->getData();
            $productIds = $data['productid'] ?? [];
            $counts = $data['count'] ?? [];

            if (count($productIds) !== count($counts)) {
                $validator->errors()->add('count', 'Every product must have exactly one quantity.');
                return;
            }

            $origOrderId = trim((string) ($data['original_orderid'] ?? ''));
            if ($origOrderId !== '') {
                $origItems = SaleData::where('orderid', $origOrderId)->get();
                if ($origItems->isEmpty()) {
                    $validator->errors()->add('original_orderid', "Original order #{$origOrderId} does not exist in sales records.");
                    return;
                }

                // Check that original order was an actual sale
                $positiveCount = $origItems->where('count', '>', 0)->sum('count');
                if ($positiveCount <= 0) {
                    $validator->errors()->add('original_orderid', "Order #{$origOrderId} is a return entry. Returns cannot be linked to another return.");
                    return;
                }

                // Verify customer matches
                $origCustomer = (string) $origItems->first()->customer_id;
                if (!empty($data['customerid']) && (string) $data['customerid'] !== $origCustomer) {
                    $validator->errors()->add('customerid', "Customer ID must match the original order customer (#{$origCustomer}).");
                }

                // Existing returns linked to this order
                $existingReturns = SaleData::where('original_orderid', $origOrderId)->get()->groupBy('product_id');

                foreach ($productIds as $idx => $prodId) {
                    $origProdRows = $origItems->where('product_id', $prodId);
                    if ($origProdRows->isEmpty()) {
                        $validator->errors()->add("productid.{$idx}", "Product {$prodId} does not belong to original order #{$origOrderId}.");
                        continue;
                    }

                    $origSold = (int) $origProdRows->where('count', '>', 0)->sum('count');
                    $alreadyReturned = abs((int) ($existingReturns->get($prodId)?->sum('count') ?? 0));
                    $remaining = max(0, $origSold - $alreadyReturned);

                    $requestedQty = abs((int) ($counts[$idx] ?? 0));
                    if ($requestedQty > $remaining) {
                        $validator->errors()->add("count.{$idx}", "Cannot return {$requestedQty} units of product {$prodId}. Only {$remaining} remaining on original order #{$origOrderId} ({$origSold} sold, {$alreadyReturned} already returned).");
                    }
                }
            }
        }];
    }
}
