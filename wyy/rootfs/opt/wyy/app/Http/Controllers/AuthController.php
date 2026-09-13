<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\UserSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly UserSessionService $sessions,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (config('app.deployment') === 'homeassistant' && ! User::query()->exists()) {
            return redirect()->route('ha.setup.create');
        }

        return view('auth.login', [
            'persistentLoginEnabled' => (bool) $this->settings->get('security', 'persistent_login_enabled', true),
            'rememberByDefault' => (bool) $this->settings->get('security', 'remember_default', true),
            'rememberDays' => (int) $this->settings->get('security', 'remember_days', 180),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user && $user->status === UserStatus::Inactive && Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'Dieses Benutzerkonto ist deaktiviert.'])->onlyInput('email');
        }

        $persistentLoginEnabled = (bool) $this->settings->get('security', 'persistent_login_enabled', true);
        $remember = $persistentLoginEnabled
            && ($request->has('remember')
                ? $request->boolean('remember')
                : (bool) $this->settings->get('security', 'remember_default', true));

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors(['email' => 'Die Anmeldedaten konnten nicht verifiziert werden.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->update(['last_login_at' => now()]);

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function profile(Request $request): View
    {
        return view('auth.profile', [
            'user' => $request->user(),
            'sessions' => $this->sessions->sessionsForUser($request->user(), $request),
            'sessionManagementAvailable' => $this->sessions->isAvailable(),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($request->user()->id)],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Profil aktualisiert.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $minLength = (int) $this->settings->get('security', 'password_min_length', 8);

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:'.$minLength, 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Das aktuelle Passwort ist nicht korrekt.']);
        }

        $request->user()->update(['password' => $data['password']]);
        $request->user()->forceFill(['must_change_password' => false])->save();

        if ((bool) $this->settings->get('security', 'invalidate_sessions_on_password_change', true)) {
            $hasRememberCookie = (bool) $request->cookies->get(Auth::guard()->getRecallerName());
            $shouldKeepPersistentLogin = (bool) $this->settings->get('security', 'persistent_login_enabled', true)
                && ($hasRememberCookie || Auth::viaRemember());

            $this->sessions->revokeOtherSessions(
                $request->user(),
                $request->session()->getId(),
                $shouldKeepPersistentLogin,
            );
        }

        return back()->with('status', 'Passwort aktualisiert.');
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $keepPersistentLogin = (bool) $this->settings->get('security', 'persistent_login_enabled', true)
            && ((bool) $request->cookies->get(Auth::guard()->getRecallerName()) || Auth::viaRemember());

        $this->sessions->revokeOtherSessions(
            $request->user(),
            $request->session()->getId(),
            $keepPersistentLogin,
        );

        return back()->with('status', 'Alle anderen Sitzungen wurden abgemeldet.');
    }

    public function logoutAllSessions(Request $request): RedirectResponse
    {
        $this->sessions->revokeAllSessions($request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Alle Geraete wurden abgemeldet.');
    }

    public function forgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data);

        return back()->with('status', 'Falls ein Konto existiert, wurde ein Reset-Link vorbereitet.');
    }

    public function showResetPassword(string $token, Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $minLength = (int) $this->settings->get('security', 'password_min_length', 8);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:'.$minLength, 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'must_change_password' => false,
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        return redirect()->route('login')->with('status', 'Passwort erfolgreich zurueckgesetzt.');
    }
}
