<?php

namespace App\Http\Requests;

use App\Models\SaleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                    $saleData = SaleData::find($this->route('id'));

                    return $saleData ? $query->where('orderid', '!=', $saleData->orderid) : $query;
                }),
            ],
            'count' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'customerid.exists' => 'The selected Customer ID was not found.',
            'productid.exists' => 'The selected Product ID was not found.',
            'orderid.unique' => 'Order ID already belongs to another sale.',
            'count.not_in' => 'Quantity cannot be 0. Enter a positive number for sales or a negative number for returns.',
            'count.min' => 'Quantity must be at least -1,000,000.',
            'count.max' => 'Quantity cannot exceed 1,000,000.',
        ];
    }
}
