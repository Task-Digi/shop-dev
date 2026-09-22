<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZeroSalesDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zero_sales_date' => ['required', 'date', 'before_or_equal:today'],
            'zero_sales_location' => ['required', Rule::in(['ALNABRU', 'MAJORSTUEN'])],
        ];
    }
}
