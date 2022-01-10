<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = [
        'user_id', 'message'
    ];

    protected $visible = [
        'id', 'message', 'created_at', 'user', 'likes_count', 'is_liked'
    ];

    protected $with = [
        'user', 'liked'
    ];

    protected $withCount = [
        'likes'
    ];

    protected $appends = ['is_liked'];

    public function likes()
    {
        return $this->belongsToMany(User::class, 'comment_like');
    }

    public function liked()
    {
        $user = auth()->user();
        return $this->likes()->where('users.id', $user ? $user->id : null);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    public function getIsLikedAttribute()
    {
        return count($this->liked) > 0;
    }
}
