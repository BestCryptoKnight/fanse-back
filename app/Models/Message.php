<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['message', 'party_id', 'direction', 'price'];

    protected $visible = [
        'id', 'message', 'media', 'created_at', 'user', 'party', 'read', 'direction'
    ];

    protected $casts = [
        'direction' => 'boolean',
        'read' => 'boolean',
    ];

    protected $with = ['accessed'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function party()
    {
        return $this->belongsTo(User::class, 'party_id');
    }

    public function media()
    {
        return $this->belongsToMany(Media::class);
    }

    public function access()
    {
        return $this->belongsToMany(User::class, 'access_message');
    }

    public function accessed()
    {
        $user = auth()->user();
        return $this->belongsToMany(User::class, 'access_message')->where('users.id', $user ? $user->id : null);
    }

    public function getIsFreeAttribute()
    {
        return $this->price == 0;
    }

    public function getHasAccessAttribute()
    {
        if ($this->isFree || count($this->accessed) > 0) {
            return true;
        }
        return false;
    }
}
