<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Code extends Model
{
    protected $table = 'codes';
    protected $primaryKey = 'code_id';
    public $incrementing = true;
    public $timestamps = false;
    protected $guarded = [];
}
