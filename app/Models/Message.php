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
}
