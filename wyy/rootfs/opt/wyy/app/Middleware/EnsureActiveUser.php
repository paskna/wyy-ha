<?php

namespace App\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh();

        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Dieses Benutzerkonto ist deaktiviert.',
            ]);
        }

        if ($user) {
            Auth::setUser($user);
        }

        if (
            $user?->mustChangePassword()
            && ! $request->routeIs('profile.edit')
            && ! $request->routeIs('profile.password')
            && ! $request->routeIs('logout')
        ) {
            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Bitte aendere zuerst dein Passwort, bevor du die App weiter nutzt.');
        }

        return $next($request);
    }
}
