<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PayoutBatch extends Model
{
    protected $dates = ['processed_at'];
    protected $appends = ['amount'];
    protected $visible = ['id', 'hash', 'amount', 'processed_at', 'created_at', 'payouts_count', 'status'];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->hash) {
                $exists = true;
                while ($exists) {
                    $model->hash = Str::random(8);
                    $exists = self::where('hash', $model->hash)->exists();
                }
            }
        });
    }

    public function payouts()
    {
        return $this->belongsToMany(Payout::class, 'batch_payout', 'batch_id');
    }

    public function getAmountAttribute()
    {
        return $this->payouts()->sum('amount') * 1;
    }
}
