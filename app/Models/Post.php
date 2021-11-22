<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $with = ['media', 'poll', 'user', 'liked'];

    protected $fillable = [
        'message', 'expires', 'schedule', 'price'
    ];

    protected $dates = [
        'schedule'
    ];

    protected $visible = [
        'id', 'message', 'expires', 'price', 'poll', 'media', 'created_at', 'user', 'likes_count', 'comments_count', 'is_liked', 'is_bookmarked', 'has_voted'
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
        return $user ? $this->likes()->where('users.id', $user->id) : [];
    }

    public function bookmarked()
    {
        $user = auth()->user();
        return $user ? $this->belongsToMany(User::class, 'bookmarks')->where('users.id', $user->id) : [];
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
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
}
