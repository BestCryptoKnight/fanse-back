<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['gateway', 'info', 'main', 'title'];

    protected $visible = ['id', 'gateway', 'main', 'title'];

    protected $casts = [
        'info' => 'array',
        'main' => 'boolean'
    ];
}
