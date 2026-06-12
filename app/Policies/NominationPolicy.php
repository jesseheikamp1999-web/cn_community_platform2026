<?php

namespace App\Policies;

use App\Models\Nomination;
use App\Models\User;

class NominationPolicy
{
    public function view(User $user, Nomination $nomination): bool
    {
        return $nomination->user_id === $user->id || $user->hasPermission('awards.review');
    }

    public function review(User $user): bool
    {
        return $user->hasPermission('awards.review');
    }
}
