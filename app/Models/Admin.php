<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;
use Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Admin extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;
    use HasApiTokens;

    const ROLE_ADMIN = 0;
    const ROLE_MODERATOR = 1;

    protected $fillable = [
        'username', 'password', 'role'
    ];

    protected $visible = [
        'id', 'username', 'role'
    ];
}
