<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'comment_id', 'message'
    ];

    protected $visible = [
        'id', 'comment_id', 'message', 'created_at', 'user', 'likes_count', 'replies_count'
    ];

    protected $with = [
        'user'
    ];

    protected $withCount = [
        'likes'
    ];

    public function likes()
    {
        return $this->belongsToMany(User::class, 'comment_like');
    }

    public function replies()
    {
        return $this->hasMany(Comment::class);
    }

    public function scopeTopLevel($query)
    {
        $query->whereNull('comment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
