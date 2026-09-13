<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminUserService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): View
    {
        $query = User::query()
            ->withCount(['userWines', 'scans', 'userWines as top_wines_count' => fn ($builder) => $builder->where('preference', 'top')]);

        if ($search = $request->string('q')->toString()) {
            $query->where(fn ($builder) => $builder
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('email', 'like', '%'.$search.'%'));
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($role = $request->string('role')->toString()) {
            $query->where('role', $role);
        }

        $sort = $request->string('sort')->toString() ?: 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $sortable = ['name', 'created_at', 'last_login_at'];
        $query->orderBy(in_array($sort, $sortable, true) ? $sort : 'name', $direction);

        $users = $query->paginate(max(5, (int) $this->settings->get('general', 'per_page', 12)))->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(Request $request, AdminUserService $service): RedirectResponse
    {
        $data = $this->validatedUserData($request);
        $service->create($request->user(), $data);

        return redirect()->route('admin.users.index')->with('status', 'Benutzer erfolgreich erstellt.');
    }

    public function show(User $user): View
    {
        $user->loadCount(['userWines', 'scans', 'userWines as top_wines_count' => fn ($builder) => $builder->where('preference', 'top')]);

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function update(Request $request, User $user, AdminUserService $service): RedirectResponse
    {
        $data = $this->validatedUserData($request, $user);
        unset($data['password']);
        $service->update($request->user(), $user, $data);

        return redirect()->route('admin.users.show', $user)->with('status', 'Benutzer aktualisiert.');
    }

    public function editPassword(User $user): View
    {
        return view('admin.users.password', compact('user'));
    }

    public function updatePassword(Request $request, User $user, AdminUserService $service): RedirectResponse
    {
        $minLength = (int) $this->settings->get('security', 'password_min_length', 10);

        $data = $request->validate([
            'password' => ['required', 'string', 'min:'.$minLength, 'confirmed'],
        ]);

        $service->resetPassword($request->user(), $user, $data['password']);

        return redirect()->route('admin.users.show', $user)->with('status', 'Temporäres Passwort gesetzt.');
    }

    public function toggleStatus(Request $request, User $user, AdminUserService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(collect(UserStatus::cases())->pluck('value')->all())],
        ]);

        $service->update($request->user(), $user, $data);

        return back()->with('status', 'Benutzerstatus aktualisiert.');
    }

    public function destroy(Request $request, User $user, AdminUserService $service): RedirectResponse
    {
        $service->delete($request->user(), $user);

        return redirect()->route('admin.users.index')->with('status', 'Benutzer deaktiviert und archiviert.');
    }

    public function destroySessions(Request $request, User $user, AdminUserService $service): RedirectResponse
    {
        $service->revokeSessions($request->user(), $user);

        return back()->with('status', 'Alle Sitzungen dieses Benutzers wurden beendet.');
    }

    private function validatedUserData(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(collect(UserRole::cases())->pluck('value')->all())],
            'status' => ['required', Rule::in(collect(UserStatus::cases())->pluck('value')->all())],
        ];

        if (! $user) {
            $rules['password'] = ['required', 'string', 'min:'.(int) $this->settings->get('security', 'password_min_length', 10), 'confirmed'];
            $rules['must_change_password'] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        if (! $user) {
            $data['must_change_password'] = $request->boolean('must_change_password')
                || (bool) $this->settings->get('security', 'force_password_change', false);
        }

        return $data;
    }
}
