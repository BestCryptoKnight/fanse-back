<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ListPivot extends Pivot
{
    const TYPE_BOOKMARK = 1;
    const TYPE_FRIENDS = 2;
    const TYPE_RESTRICTED = 3;
    const TYPE_BLOCKED = 4;

    protected $casts = [
        'list_ids' => 'array'
    ];
}
