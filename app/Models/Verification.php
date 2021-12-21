<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Str;
use Storage;

class Verification extends Model
{
    use SoftDeletes;

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_DECLINED = 2;

    protected $fillable = [
        'country', 'info', 'status', 'user_id'
    ];

    protected $casts = [
        'info' => 'array'
    ];

    protected $appends = ['photo'];

    protected $visible = [
        'id', 'country', 'info', 'status', 'photo'
    ];

    public static function boot()
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

    public function getPhotoAttribute()
    {
        return Storage::url('verifications/' . $this->hash . '.jpg');
    }
}
