<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserSessionService
{
    public function isAvailable(): bool
    {
        return config('session.driver') === 'database'
            && Schema::hasTable(config('session.table', 'sessions'));
    }

    public function sessionsForUser(User $user, Request $request): Collection
    {
        if (! $this->isAvailable()) {
            return collect();
        }

        $currentSessionId = $request->session()->getId();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function (object $row) use ($currentSessionId): array {
                return [
                    'id' => $row->id,
                    'device' => $this->describeDevice((string) ($row->user_agent ?? 'Unbekanntes Geraet')),
                    'browser' => $this->describeBrowser((string) ($row->user_agent ?? '')),
                    'ip_address' => $row->ip_address,
                    'last_activity' => Carbon::createFromTimestamp((int) $row->last_activity),
                    'signed_in_at' => Carbon::createFromTimestamp((int) $row->last_activity),
                    'is_current' => $row->id === $currentSessionId,
                ];
            });
    }

    public function revokeOtherSessions(User $user, string $currentSessionId, bool $keepPersistentLogin = true): void
    {
        if ($this->isAvailable()) {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->delete();
        }

        $this->rotateRememberToken($user, $keepPersistentLogin);
    }

    public function revokeAllSessions(User $user): void
    {
        if ($this->isAvailable()) {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->delete();
        }

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->save();
    }

    private function rotateRememberToken(User $user, bool $keepPersistentLogin): void
    {
        $freshUser = $user->fresh() ?? $user;

        $freshUser->forceFill([
            'remember_token' => Str::random(60),
        ])->save();

        if ($keepPersistentLogin && Auth::id() === $freshUser->id) {
            Auth::login($freshUser->fresh() ?? $freshUser, true);
        }
    }

    private function describeDevice(string $userAgent): string
    {
        $agent = Str::lower($userAgent);

        return match (true) {
            Str::contains($agent, ['iphone']) => 'iPhone',
            Str::contains($agent, ['ipad']) => 'iPad',
            Str::contains($agent, ['android']) => 'Android',
            Str::contains($agent, ['macintosh', 'mac os']) => 'Mac',
            Str::contains($agent, ['windows']) => 'Windows',
            default => 'Browsergeraet',
        };
    }

    private function describeBrowser(string $userAgent): string
    {
        $agent = Str::lower($userAgent);

        return match (true) {
            Str::contains($agent, ['edg/']) => 'Edge',
            Str::contains($agent, ['chrome/']) => 'Chrome',
            Str::contains($agent, ['firefox/']) => 'Firefox',
            Str::contains($agent, ['safari/']) && ! Str::contains($agent, ['chrome/', 'chromium/']) => 'Safari',
            default => 'Browser',
        };
    }
}
