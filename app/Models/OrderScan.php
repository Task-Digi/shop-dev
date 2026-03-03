<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderScan extends Model
{
    protected $table = 'order_scans';

    protected $touches = ['order'];

    protected $fillable = [
        'order_id',
        'scan_date_time',
        'ean_code',
        'units',
        'deactivated'
    ];

    public function order()
    {
        return $this->belongsTo(OrderRecord::class, 'order_id', 'order_id');
    }
}
