<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class MediaPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Media  $media
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, Media $media)
    {
        return $user->isAdmin || $media->user_id === $user->id;
    }
}
