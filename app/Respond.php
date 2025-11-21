<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Respond extends Model
{
    protected $table = 'respond';
    protected $primaryKey = 'Respond_ID';
    public $incrementing = true;
    public $timestamps = true;
    protected $guarded = [];
}
