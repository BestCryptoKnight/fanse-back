<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bundle extends Model
{
    use SoftDeletes;

    protected $fillable = ['months', 'discount'];

    protected $visible = ['id', 'months', 'discount'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
