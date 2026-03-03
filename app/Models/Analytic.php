<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analytic extends Model
{
    protected $table = 'analytics';
    protected $primaryKey = 'Analytics_ID';
    public $incrementing = true;
    public $timestamps = false;
    protected $guarded = [];
}
