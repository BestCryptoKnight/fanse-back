<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomList extends Model
{
    const DEFAULT_BOOKMARKS = 0;
    const DEFAULT_FANS = 1;
    const DEFAULT_FOLLOWING = 2;
    const DEFAULT_RECENT = 3;

    protected $fillable = ['title', 'id', 'user_id'];

    protected $visible = [
        'id', 'title', 'listees_count'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getListeesCountAttribute()
    {
        return $this->user->listees()->whereJsonContains('list_ids', $this->id)->count();
    }

    public static function bookmarks(User $user)
    {
        return new self([
            'id' => self::DEFAULT_BOOKMARKS,
            'user_id' => $user->id,
        ]);
    }
}
