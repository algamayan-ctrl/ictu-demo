<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestNumberAlias extends Model
{
    public $timestamps = false;
    protected $fillable = ['request_id', 'alias', 'migration_batch', 'created_at'];
}
