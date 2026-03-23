<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderScan extends Model
{
    protected $table = 'order_scans';

    protected $fillable = [
        'order_id',
        'scan_date_time',
        'ean_code',
        'units',
        'deactivated'
    ];
}
