<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderRecord extends Model
{
    protected $table = 'order_records';

    protected $fillable = [
        'order_id',
        'order_date',
        'planned_delivery',
        'delivery_handling_date',
        'status',
        'completed',
        'reopen_password',
        'note',
        'staff'
    ];
    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'order_id');
    }

    public function scans()
    {
        return $this->hasMany(OrderScan::class, 'order_id', 'order_id');
    }
}
