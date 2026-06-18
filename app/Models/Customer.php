<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    public $timestamps = false; // Disables timestamps
    protected $table = 'customers';

    protected $fillable = [
        'customer_id',
        'customer_name',
        'KS_exists',
        'crm_exists',
        'crm_link',
        'crm_id'
    ];
}
