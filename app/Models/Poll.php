<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    public $timestamps = false;
    protected $fillable = ['option'];
    protected $visible = ['option', 'id', 'votes_count'];

    protected $with = ['voted'];
    protected $withCount = ['votes'];

    public function votes()
    {
        return $this->belongsToMany(User::class, 'votes');
    }

    public function voted()
    {
        $user = auth()->user();
        return $user ? $this->votes()->where('users.id', $user->id) : $this->votes()->limit(0);
    }

    public function getHasVotedAttribute()
    {
        return count($this->voted) > 0;
    }
}
