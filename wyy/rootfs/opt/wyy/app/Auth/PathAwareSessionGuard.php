<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;

class PathAwareSessionGuard extends SessionGuard
{
    public function getRecallerName()
    {
        $suffix = substr(
            sha1((string) config('session.cookie', 'weinassistent-session').'|'.(string) config('session.path', '/')),
            0,
            16,
        );

        return 'remember_'.$this->name.'_'.$suffix;
    }
}
