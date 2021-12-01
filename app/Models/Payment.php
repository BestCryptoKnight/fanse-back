<?php

namespace App\Models;

use App\Providers\Payment\Contracts\Payment as ContractsPayment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Payment extends Model
{
    use SoftDeletes;

    const TYPE_SUBSCRIPTION_NEW = 0;
    const TYPE_SUBSCRIPTION_RENEW = 1;
    const TYPE_POST = 10;
    const TYPE_MESSAGE = 11;

    const STATUS_PENDING = 0;
    const STATUS_COMPLETE = 1;
    const STATUS_REFUNDED = 10;

    protected $fillable = [
        'type', 'token', 'gateway', 'amount', 'currency', 'info', 'status', 'user_id', 'hash', 'status'
    ];

    protected $visible = [
        'type', 'hash', 'gateway', 'amount', 'currency', 'info', 'status'
    ];

    protected $casts = [
        'info' => 'array'
    ];

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
