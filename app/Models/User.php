<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;
use Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable;

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

    public function bookmarks()
    {
        return $this->belongsToMany(Post::class, 'bookmarks');
    }

    public function lists()
    {
        return $this->hasMany(CustomList::class);
    }

    public function listees()
    {
        return $this->belongsToMany(User::class, 'lists', 'user_id', 'listee_id')->withPivot('list_ids')->using(ListPivot::class);
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
