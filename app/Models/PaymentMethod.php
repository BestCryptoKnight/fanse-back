<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    const TYPE_CARD = 0;

    protected $fillable = ['type', 'info', 'main', 'title'];

    protected $visible = ['id', 'type', 'main', 'title'];

    protected $casts = [
        'info' => 'array',
        'main' => 'boolean'
    ];
}
