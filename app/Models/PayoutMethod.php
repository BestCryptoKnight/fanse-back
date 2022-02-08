<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutMethod extends Model
{
    protected $fillable = ['gateway', 'info', 'main', 'user_id'];
    protected $visible = ['gateway', 'info', 'main', 'id'];
    protected $casts = ['info' => 'array', 'main' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
