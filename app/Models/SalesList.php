<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class SalesList extends Model
{
    protected $table = 'sales_lists';

    protected $fillable = [
        'date',
        'location',
        'type',
        'payment',
        'customerid',
        'orderid',
        'productid',
        'count',
        // Add other fields as needed
    ];
}

