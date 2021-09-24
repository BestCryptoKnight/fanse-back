<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $with = ['media', 'poll'];

    protected $fillable = [
        'message', 'expires', 'schedule', 'price'
    ];

    protected $dates = [
        'schedule'
    ];

    protected $visible = [
        'id', 'message', 'expires', 'price', 'poll', 'media', 'created_at'
    ];

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

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
