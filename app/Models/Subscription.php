<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'sub_id', 'token', 'gateway', 'expires', 'info', 'amount', 'active'
    ];

    protected $dates = [
        'expires'
    ];

    protected $casts = [
        'info' => 'array',
        'active' => 'bool'
    ];

    protected $visible = [
        'id', 'sub', 'expires', 'info', 'amount', 'active'
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

    public function sub()
    {
        return $this->belongsTo(User::class, 'sub_id');
    }
}
