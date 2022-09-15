<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    const TYPE_ACTIVE = 0;
    const TYPE_SCHEDULED = 1;
    const TYPE_EXPIRED = 2;

    protected $with = ['media', 'poll', 'user', 'liked', 'accessed'];

    protected $fillable = [
        'message', 'expires', 'schedule', 'price'
    ];

    protected $dates = [
        'schedule'
    ];

    protected $visible = [
        'id', 'message', 'expires', 'price', 'poll', 'media', 'published_at', 'user',
        'likes_count', 'comments_count', 'is_liked', 'is_bookmarked', 'has_voted', 'has_access'
    ];

    protected $withCount = [
        'likes', 'comments'
    ];

    protected $appends = ['is_liked', 'is_bookmarked', 'has_voted', 'has_access', 'published_at'];

    public function scopeActive($query)
    {
        $now = Carbon::now('UTC');
        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('schedule')->orWhere('schedule', '<', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires')
                    ->orWhereRaw('DATE_ADD(IF(schedule IS NULL, created_at, schedule), INTERVAL expires DAY) > ?', [$now]);
            });
    }

    public function scopeExpired($query)
    {
        $now = Carbon::now('UTC');
        return $query->whereNotNull('expires')->whereRaw('DATE_ADD(IF(schedule IS NULL, created_at, schedule), INTERVAL expires DAY) < ?', [$now]);
    }

    public function scopeScheduled($query)
    {
        $now = Carbon::now('UTC');
        return $query->whereNotNull('schedule')->where('schedule', '>', $now);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function media()
    {
        return $this->belongsToMany(Media::class);
    }

    public function poll()
    {
        return $this->hasMany(Poll::class);
    }

    public function likes()
    {
        return $this->belongsToMany(User::class, 'like_post');
    }

    public function liked()
    {
        $user = auth()->user();
        return $this->likes()->where('users.id', $user ? $user->id : null);
    }

    public function bookmarked()
    {
        $user = auth()->user();
        return $this->belongsToMany(User::class, 'bookmarks')->where('users.id', $user ? $user->id : null);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function access()
    {
        return $this->belongsToMany(User::class, 'access_post');
    }

    public function accessed()
    {
        $user = auth()->user();
        return $this->belongsToMany(User::class, 'access_post')->where('users.id', $user ? $user->id : null);
    }

    public function getIsLikedAttribute()
    {
        return count($this->liked) > 0;
    }

    public function getIsBookmarkedAttribute()
    {
        return count($this->bookmarked) > 0;
    }

    public function getHasVotedAttribute()
    {
        foreach ($this->poll as $p) {
            if ($p->hasVoted) {
                return true;
            }
        }
        return false;
    }

    public function getIsFreeAttribute()
    {
        return $this->price == 0;
    }

    public function getHasAccessAttribute()
    {
        $user = auth()->user();
        if ($user && ($user->isAdmin || $this->user->id == auth()->user()->id)) {
            return true;
        }
        if ($this->user->isSubscribed) {
            return true;
        }
	if ($this->user->id == '205') {
            return true;
        }
        return false;
    }

    public function getPublishedAtAttribute()
    {
        return max($this->schedule, $this->created_at);
    }

    public function toArray()
    {
        if (!$this->hasAccess) {
            foreach ($this->media as $m) {
                $m->makeHidden(['url', 'screenshot']);
            }
        }
        return parent::toArray();
    }
}
