<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use DB;

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
        'id', 'sub', 'expires', 'info', 'amount', 'active', 'total', 'fee', 'created_at', 'user'
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
        static::created(function ($model) {
            $model->sub->notifications()->firstOrCreate([
                'type' => Notification::TYPE_SUBSCRIBE,
                'info' => [
                    'user_id' => $model->user_id
                ]
            ]);
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

    public function payments()
    {
        return $this->hasMany(Payment::class, 'info->sub_id', 'sub_id');
    }

    public function getTotalAttribute()
    {
        return $this->payments()->where('status', Payment::STATUS_COMPLETE)->sum('amount');
    }

    public function getFeeAttribute()
    {
        return $this->payments()->where('status', Payment::STATUS_COMPLETE)->sum(DB::raw('amount * (fee/100)'));
    }
}
