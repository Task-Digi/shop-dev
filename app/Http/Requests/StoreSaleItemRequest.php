<?php

namespace App\Http\Requests;

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
        return [
            'date' => ['required', 'date'],
            'location' => ['required', Rule::in(['ALNABRU', 'MAJORSTUEN'])],
            'type' => ['required', Rule::in(['MalProff MPP', 'FARGERIKE'])],
            'payment' => ['required', Rule::in(['Invoice', 'Cash/Card'])],
            'customerid' => ['required', 'string', 'max:255', 'exists:customers,customer_id'],
            'orderid' => ['required', 'string', 'max:255', 'unique:sales_lists,orderid'],
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
            if (count($this->input('productid', [])) !== count($this->input('count', []))) {
                $validator->errors()->add('count', 'Every product must have exactly one quantity.');
            }
        }];
    }
}
