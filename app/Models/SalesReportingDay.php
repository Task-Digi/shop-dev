<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesReportingDay extends Model
{
    protected $fillable = [
        'date',
        'location',
        'client',
        'sales_amount',
        'confirmation_source',
    ];

    protected $casts = [
        'date' => 'date',
        'sales_amount' => 'decimal:2',
    ];
}
