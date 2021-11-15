<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

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
        return $user ? $this->likes()->where('users.id', $user->id) : [];
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
