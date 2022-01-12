<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PaymentMethodPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can update the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentMethod  $paymentMethod
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function update(User $user, PaymentMethod $paymentMethod)
    {
        return $user->isAdmin || $paymentMethod->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\PaymentMethod  $paymentMethod
     * @return \Illuminate\Auth\Access\Response|bool
     */
    public function delete(User $user, PaymentMethod $paymentMethod)
    {
        return $user->isAdmin || $paymentMethod->user_id === $user->id;
    }
}
