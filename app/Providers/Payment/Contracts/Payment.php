<?php

namespace App\Providers\Payment\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Payment extends Model
{
    const STATUS_PENDING = 0;
    const STATUS_COMPLETE = 1;
    const STATUS_REFUNDED = 10;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->hash) {
                $exists = true;
                while ($exists) {
                    $model->hash = Str::random();
                    $exists = self::where('hash', $model->hash)->exists();
                }
            }
        });
    }
}
