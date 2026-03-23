<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public $timestamps = false; // Disables timestamps
    protected $table = 'products';

    protected $fillable = [
        'product_id',
        'product_name',
        'price',
        'retail',
        'ean_code'
    ];
}
