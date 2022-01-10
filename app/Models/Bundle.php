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

    public function getPriceAttribute()
    {
        return round($this->user->price * $this->months * (1 - $this->discount / 100));
    }
}
