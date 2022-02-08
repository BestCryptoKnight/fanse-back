<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payout extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 0;
    const STATUS_COMPLETE = 1;

    protected $fillable = ['amount', 'status', 'info'];
    protected $visible = ['amount', 'status', 'info', 'id', 'created_at', 'updated_at', 'processed_at', 'batch', 'user'];
    protected $casts = ['info' => 'array'];
    protected $dates = ['processed_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeComplete($q)
    {
        $q->where('status', self::STATUS_COMPLETE);
    }

    public function scopePending($q)
    {
        $q->where('status', self::STATUS_PENDING);
    }

    public function batches()
    {
        return $this->belongsToMany(PayoutBatch::class, 'batch_payout', 'payout_id', 'batch_id');
    }

    public function getBatchAttribute()
    {
        return count($this->batches) ? $this->batches[0] : null;
    }
}
