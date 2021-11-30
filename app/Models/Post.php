<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $with = ['media', 'poll', 'user', 'liked', 'accessed'];

    protected $fillable = [
        'message', 'expires', 'schedule', 'price'
    ];

    protected $dates = [
        'schedule'
    ];

    protected $visible = [
        'id', 'message', 'expires', 'price', 'poll', 'media', 'created_at', 'user',
        'likes_count', 'comments_count', 'is_liked', 'is_bookmarked', 'has_voted', 'has_access'
    ];

    protected $withCount = [
        'likes', 'comments'
    ];

    protected $appends = ['is_liked', 'is_bookmarked', 'has_voted'];

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
        if ($this->user->isFree) {
            if ($this->isFree) {
                return true;
            } else if (count($this->accessed) > 0) {
                return true;
            }
        } else if ($this->user->isSubscribed) {
            return true;
        }
        return false;
    }
}
