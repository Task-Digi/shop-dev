<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    protected $table = 'respons';
    protected $primaryKey = 'Respons_ID';
    public $incrementing = true;
    public $timestamps = true;
    protected $guarded = [];
}
