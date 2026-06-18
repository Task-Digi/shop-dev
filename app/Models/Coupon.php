<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $table = 'coupons';

    protected $casts = [
        'end_date' => 'datetime',
    ];

    protected $fillable = [
        'mobile_nr',
        'end_date',
        'voucher',
        'used'
    ];

    public function coupons()
    {
        return $this->hasMany(Coupon::class, 'mobile_nr', 'mobile_nr')->where('id', '<>', $this->id);
    }
}
