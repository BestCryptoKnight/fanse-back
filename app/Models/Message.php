<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class Message extends Model
{
    protected $fillable = ['message', 'price', 'mass'];

    protected $visible = [
        'id', 'message', 'media', 'created_at', 'user', 'party', 'read', 'has_access', 'price'
    ];

    protected $with = ['accessed'];

    protected $appends = ['has_access', 'read'];

    protected $casts = [
        'mass' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
        $user = auth()->user();
        if ($user && $this->user->id == $user->id) {
            return true;
        } else if ($this->isFree || count($this->accessed) > 0) {
            return true;
        }
        return false;
    }

    public function getPartyAttribute()
    {
        if ($this->pivot) {
            return User::find($this->pivot->party_id);
        }
        return null;
    }

    public function getReadAttribute()
    {
        if ($this->pivot) {
            return $this->pivot->read;
        }
        return false;
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

    public function getRecipientsCountAttribute()
    {
        return DB::table('message_user')
            ->where('message_id', $this->id)
            ->where('user_id', '<>', $this->user_id)
            ->count();
    }
}
