<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomList extends Model
{
    protected $fillable = ['title'];

    protected $visible = [
        'id', 'title'
    ];
}
