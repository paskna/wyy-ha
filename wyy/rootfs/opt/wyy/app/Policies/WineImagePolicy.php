<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WineImage;

class WineImagePolicy
{
    public function view(User $user, WineImage $wineImage): bool
    {
        return $wineImage->user_id === $user->id;
    }
}
