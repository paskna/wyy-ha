<?php

namespace App\Middleware;

use App\Services\InstallationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function __construct(private readonly InstallationService $installation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        if ($this->installation->isInstalled()) {
            return $next($request);
        }

        if ($request->routeIs('install.*') || $request->routeIs('pwa.*') || $request->routeIs('health') || $request->is('build/*')) {
            return $next($request);
        }

        return redirect()->route('install.welcome');
    }
}
