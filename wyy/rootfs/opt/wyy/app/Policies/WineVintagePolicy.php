<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WineVintage;

class WineVintagePolicy
{
    public function view(User $user, WineVintage $wineVintage): bool
    {
        return $user->status->value === 'active';
    }

    public function update(User $user, WineVintage $wineVintage): bool
    {
        return $this->view($user, $wineVintage)
            && $wineVintage->userWines()->where('user_id', $user->id)->exists();
    }
}
