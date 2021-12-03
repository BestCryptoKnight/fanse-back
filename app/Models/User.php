<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;
use Auth;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, SoftDeletes, Notifiable, MustVerifyEmail;

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
        'website',
        'price'
    ];

    protected $visible = [
        'id', 'username', 'name', 'role', 'avatar', 'cover', 'price', 'is_subscribed', 'bundles'
    ];

    protected $with = ['subscribed'];
    protected $appends = ['is_subscribed', 'is_free'];

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
            ->makeVisible(['bio', 'location', 'website', 'email'])
            ->load(['bundles']);
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
        return $this->belongsToMany(Post::class, 'bookmarks')->withTimestamps();
    }

    public function lists()
    {
        return $this->hasMany(CustomList::class);
    }

    public function listees()
    {
        return $this->belongsToMany(User::class, 'lists', 'user_id', 'listee_id')->withPivot('list_ids')->using(ListPivot::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function mailbox()
    {
        return $this->belongsToMany(Message::class)->withPivot(['party_id', 'read'])->using(MessagePivot::class);
    }

    public function bundles()
    {
        return $this->hasMany(Bundle::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscribers()
    {
        return $this->belongsToMany(User::class, 'subscriptions', 'sub_id', 'user_id');
    }

    public function subscribed()
    {
        $user = auth()->user();
        return $this->subscribers()->where('subscriptions.user_id', $user ? $user->id : null);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getIsSubscribedAttribute()
    {
        return count($this->subscribed) > 0;
    }

    public function getIsFreeAttribute()
    {
        return $this->price == 0;
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
