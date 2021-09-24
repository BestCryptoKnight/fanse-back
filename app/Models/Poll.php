<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    public $timestamps = false;
    protected $fillable = ['option'];
    protected $visible = ['option', 'id'];
}
