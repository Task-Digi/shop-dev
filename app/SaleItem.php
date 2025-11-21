<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
     protected $fillable = [
        'date',
        'location',
        'type',
        'payment',
        'customerid',
        'productid',
        'productid2',
        'productid3',
        'productid4',
        'productid5',
        'productid6',
        'productid7',
        'productid8',
        'productid9',
        'productid10',
        'orderid',
        'count',
        'count2',
        'count3',
        'count4',
        'count5',
        'count6',
        'count7',
        'count8',
        'count9',
        'count10',
        // Add other fields as needed
    ];
}
