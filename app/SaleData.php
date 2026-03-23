<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

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
        'product_id',
        'product_name',
        'price',
        'retail',
        'count'

        // Add other fields as needed
    ];
}
