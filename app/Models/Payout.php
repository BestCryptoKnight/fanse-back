<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payout extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 0;
    const STATUS_COMPLETE = 1;

    protected $fillable = ['amount', 'status', 'info'];
    protected $visible = ['amount', 'status', 'info'];
    protected $casts = ['info' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
