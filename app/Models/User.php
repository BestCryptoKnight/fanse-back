<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;
use Auth;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use SoftDeletes, Notifiable;

    const ROLE_USER = 0;
    const ROLE_CREATOR = 1;

    const CHANNEL_EMAIL = 0;
    const CHANNEL_GOOGLE = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'channel_type',
        'channel_id',
        'bio',
        'location',
        'website'
    ];


    protected $visible = [
        'username', 'name', 'role', 'avatar', 'cover'
    ];

    protected static function boot()
    {
        parent::boot();
        User::creating(function ($model) {
            if (!$model->username) {
                $exists = true;
                while ($exists) {
                    $model->username = 'user' . rand(10000, 10000000);
                    $exists = self::where('username', $model->username)->exists();
                }
            }
        });
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function makeAuth()
    {
        $this->refresh()
            ->makeVisible(['bio', 'location', 'website'])
            ->load([]);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    public function getAvatarAttribute($value)
    {
        return $value ? Storage::url('profile/avatar/' . $this->id . '.jpg') : null;
    }

    public function getCoverAttribute($value)
    {
        return $value ? Storage::url('profile/cover/' . $this->id . '.jpg') : null;
    }
}
