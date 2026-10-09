<?php

namespace App\Http\Requests;

use App\Models\SaleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSaleItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'location' => ['required', Rule::in(['ALNABRU', 'MAJORSTUEN'])],
            'type' => ['required', Rule::in(['MalProff MPP', 'FARGERIKE'])],
            'payment' => ['required', Rule::in(['Invoice', 'Cash/Card'])],
            'customerid' => ['required', 'string', 'max:255', 'exists:customers,customer_id'],
            'productid' => ['required', 'string', 'max:255', 'exists:products,product_id'],
            'orderid' => [
                'required', 'string', 'max:255',
                Rule::unique('sales_lists', 'orderid')->where(function ($query) {
                    $saleData = SaleData::query()->find($this->route('id'));

                    return $saleData ? $query->where('orderid', '!=', $saleData->orderid) : $query;
                }),
            ],
            'original_orderid' => ['nullable', 'string', 'max:255', 'different:orderid'],
            'is_return' => ['nullable'],
            'count' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customerid.exists' => 'The selected Customer ID was not found.',
            'productid.exists' => 'The selected Product ID was not found.',
            'orderid.unique' => 'Order ID already belongs to another sale.',
            'original_orderid.different' => 'Original Order ID cannot be the same as the Order ID.',
            'count.not_in' => 'Quantity cannot be 0. Enter a positive number for sales or a negative number for returns.',
            'count.min' => 'Quantity must be at least -1,000,000.',
            'count.max' => 'Quantity cannot exceed 1,000,000.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $data = $validator->getData();
            $origOrderId = trim((string) ($data['original_orderid'] ?? ''));
            if ($origOrderId !== '') {
                $origItems = SaleData::where('orderid', $origOrderId)->get();
                if ($origItems->isEmpty()) {
                    $validator->errors()->add('original_orderid', "Original order #{$origOrderId} does not exist in sales records.");
                    return;
                }

                $positiveCount = $origItems->where('count', '>', 0)->sum('count');
                if ($positiveCount <= 0) {
                    $validator->errors()->add('original_orderid', "Order #{$origOrderId} is a return entry. Returns cannot be linked to another return.");
                    return;
                }

                $prodId = (string) ($data['productid'] ?? '');
                $origProdRows = $origItems->where('product_id', $prodId);
                if ($origProdRows->isEmpty()) {
                    $validator->errors()->add('productid', "Product {$prodId} does not belong to original order #{$origOrderId}.");
                    return;
                }

                // Check remaining available return quantity (excluding current record if it was already linked)
                $currentId = $this->route('id');
                $existingReturns = SaleData::where('original_orderid', $origOrderId)
                    ->where('product_id', $prodId)
                    ->where('id', '!=', $currentId)
                    ->get();

                $origSold = (int) $origProdRows->where('count', '>', 0)->sum('count');
                $alreadyReturned = abs((int) $existingReturns->sum('count'));
                $remaining = max(0, $origSold - $alreadyReturned);

                $requestedQty = abs((int) ($data['count'] ?? 0));
                if ($requestedQty > $remaining) {
                    $validator->errors()->add('count', "Cannot return {$requestedQty} units of product {$prodId}. Only {$remaining} remaining on original order #{$origOrderId} ({$origSold} sold, {$alreadyReturned} already returned).");
                }
            }
        }];
    }
}
