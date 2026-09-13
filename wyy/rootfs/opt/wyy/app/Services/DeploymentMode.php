<?php

namespace App\Services;

use Illuminate\Http\Request;

class DeploymentMode
{
    public function isHomeAssistant(): bool
    {
        return config('app.deployment') === 'homeassistant';
    }

    public function ingressPath(Request $request): ?string
    {
        if (! $this->isHomeAssistant()) {
            return null;
        }

        $path = (string) ($request->headers->get('X-Ingress-Path') ?: $request->headers->get('X-Forwarded-Prefix'));
        $path = '/'.trim($path, '/');

        return $path === '/' || ! str_starts_with($path, '/') ? null : $path;
    }

    public function isIngressRequest(Request $request): bool
    {
        return $this->ingressPath($request) !== null;
    }
}
