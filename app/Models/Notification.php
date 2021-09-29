<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    const TYPE_COMMENT = 0;
    const TYPE_LIKE = 10;
    const TYPE_COMMENT_LIKE = 11;
    const TYPE_SUBSCRIBE = 20;
    const TYPE_TIP = 30;
    const TYPE_PROMO = 40;

    protected $fillable = [
        'type', 'info'
    ];

    protected $casts = [
        'info' => 'array'
    ];

    protected $visible = [
        'type', 'user', 'viewed', 'id', 'items'
    ];

    protected $appends = [
        'items'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getItemsAttribute()
    {
        $items = [];
        foreach ($this->info as $k => $v) {
            switch ($k) {
                case 'comment_id':
                    $items['comment'] = Comment::find($v);
                    break;
                case 'post_id':
                    $items['post'] = Post::find($v);
                    break;
                case 'user_id':
                    $items['user'] = User::find($v);
                    break;
            }
        }
        return $items;
    }
}
