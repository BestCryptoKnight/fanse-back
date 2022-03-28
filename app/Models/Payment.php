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
    const TYPE_TIP = 20;

    const STATUS_PENDING = 0;
    const STATUS_COMPLETE = 1;
    const STATUS_REFUNDED = 10;

    protected $fillable = [
        'type', 'token', 'gateway', 'amount', 'info', 'status', 'user_id', 'hash', 'status', 'to_id', 'fee'
    ];

    protected $visible = [
        'id', 'type', 'hash', 'gateway', 'amount', 'info', 'status', 'fee', 'user', 'items', 'to', 'created_at'
    ];

    protected $casts = [
        'info' => 'array'
    ];

    protected $appends = [
        'items'
    ];

    protected $with = ['user', 'to'];

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
            if (!$model->fee) {
                $model->fee = config('misc.payment.commission');
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function to()
    {
        return $this->belongsTo(User::class, 'to_id');
    }

    public function getItemsAttribute()
    {
        $items = [];
        foreach ($this->info as $k => $v) {
            switch ($k) {
                case 'comment_id':
                    $items['comment'] = Comment::find($v);
                    break;
                case 'post_id':
                    $items['post'] = Post::find($v);
                    break;
                case 'sub_id':
                    $items['sub'] = User::find($v);
                    break;
                case 'bundle_id':
                    $items['bundle'] = Bundle::find($v);
                    break;
            }
        }
        return $items;
    }

    public function scopeComplete($q)
    {
        $q->where('status', self::STATUS_COMPLETE);
    }
}
