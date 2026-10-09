<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class SaleData extends Model
{
    protected $table = 'sale_data';

    protected $fillable = [
        'date',
        'location',
        'type',
        'payment',
        'customer_id',
        'customer_name',
        'crm_exists',
        'crm_link',
        'crm_id',
        'orderid',
        'original_orderid',
        'product_id',
        'product_name',
        'price',
        'retail',
        'count',
        'sales_list_id',

        // Add other fields as needed
    ];

    public function isReturn(): bool
    {
        return (int) $this->count < 0 || !empty($this->original_orderid);
    }
}
