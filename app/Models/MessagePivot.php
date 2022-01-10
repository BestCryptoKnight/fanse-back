<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MessagePivot extends Pivot
{
    protected $casts = [
        'read' => 'boolean'
    ];
}
