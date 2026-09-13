<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeAssistantSetupController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function create(): View|RedirectResponse
    {
        $this->ensureHomeAssistantMode();

        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        return view('auth.home-assistant-setup');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureHomeAssistantMode();

        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'app_name' => ['nullable', 'string', 'max:80'],
        ]);

        DB::transaction(function () use ($data): void {
            User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'timezone' => config('app.timezone', 'Europe/Zurich'),
            ]);

            if (! empty($data['app_name'])) {
                $this->settings->set('general', 'app_name', $data['app_name']);
            }
        });

        return redirect()->route('login')->with('status', 'WYY ist eingerichtet. Du kannst dich jetzt anmelden.');
    }

    private function ensureHomeAssistantMode(): void
    {
        abort_unless(config('app.deployment') === 'homeassistant', 404);
    }
}
