<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Access extends Model
{
    protected $table = 'access';
    protected $primaryKey = 'Access_ID';
    public $incrementing = true;
    public $timestamps = false;
    protected $guarded = [];
}
