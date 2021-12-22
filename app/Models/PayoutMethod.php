<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayoutMethod extends Model
{
    const TYPE_PAYPAL = 0;
    const TYPE_BANK = 1;

    protected $fillable = ['type', 'info', 'main', 'user_id'];
    protected $visible = ['type', 'info', 'main', 'id'];
    protected $casts = ['info' => 'array', 'main' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
