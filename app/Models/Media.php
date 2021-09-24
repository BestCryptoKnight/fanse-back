<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Storage;

class Media extends Model
{
    use SoftDeletes;

    const TYPE_IMAGE = 0;
    const TYPE_VIDEO = 1;
    const TYPE_AUDIO = 2;

    const STATUS_TMP = 0;
    const STATUS_CONVERTING = 1;
    const STATUS_ACTIVE = 2;

    protected $fillable = [
        'type', 'status', 'extension'
    ];

    protected $visible = [
        'id', 'type', 'created_at', 'url'
    ];

    protected $appends = [
        'url'
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute()
    {
        return Storage::url(($this->status == self::STATUS_TMP ? 'tmp' : 'media')
            . '/' . $this->hash . '.' . $this->extension);
    }

    public function getPathAttribute()
    {
        return ($this->status == self::STATUS_TMP ? 'tmp' : 'media') . '/' . $this->hash . '.' . $this->extension;
    }

    public function publish()
    {
        if ($this->status == self::STATUS_TMP) {
            Storage::move($this->path, 'media' . '/' . $this->hash . '.' . $this->extension);
            $this->status = self::STATUS_ACTIVE;
            $this->save();
        }
    }
}
