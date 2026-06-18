<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'order_id2',
        'order_date',
        'ordered_by',
        'planned_delivery',
        'status',
        'your_reference',
        'sku',
        'item_name',
        'packaging_quantity',
        'packaging_unit',
        'ordered_quantity',
        'delivered',
        'quantity',
        'price'
    ];
    public function product()
    {
        return $this->belongsTo(Product::class, 'sku', 'product_id');
    }
}
