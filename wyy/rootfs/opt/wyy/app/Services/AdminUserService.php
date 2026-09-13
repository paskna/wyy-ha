<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminUserService
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly UserSessionService $sessions,
    ) {}

    public function create(User $admin, array $data): User
    {
        if (! array_key_exists('must_change_password', $data)) {
            $data['must_change_password'] = false;
        }

        $user = User::query()->create($data);
        $this->auditLog->log($admin, $user, 'user_created', [
            'role' => $user->role->value,
            'status' => $user->status->value,
            'must_change_password' => $user->must_change_password,
        ]);

        return $user;
    }

    public function update(User $admin, User $target, array $data): User
    {
        return DB::transaction(function () use ($admin, $target, $data) {
            $originalRole = $target->role;
            $originalStatus = $target->status;

            if ($admin->is($target) && (($data['status'] ?? $target->status->value) !== UserStatus::Active->value || ($data['role'] ?? $target->role->value) !== UserRole::Admin->value)) {
                throw ValidationException::withMessages([
                    'role' => 'Du kannst dein aktuell angemeldetes Administratorkonto nicht selbst deaktivieren oder herabstufen.',
                ]);
            }

            $this->protectLastAdmin($target, $data['role'] ?? $target->role->value, $data['status'] ?? $target->status->value, false);

            $target->fill($data);
            $target->save();

            if (($data['status'] ?? null) === UserStatus::Inactive->value) {
                $this->sessions->revokeAllSessions($target);
            }

            if (($data['role'] ?? null) && $originalRole !== $target->role) {
                $this->auditLog->log($admin, $target, 'role_changed', [
                    'from' => $originalRole->value,
                    'to' => $target->role->value,
                ]);
            }

            if (($data['status'] ?? null) && $originalStatus !== $target->status) {
                $this->auditLog->log($admin, $target, $target->status === UserStatus::Active ? 'user_activated' : 'user_deactivated');
            }

            $this->auditLog->log($admin, $target, 'user_updated', [
                'fields' => array_keys(array_diff_key($data, array_flip(['password']))),
            ]);

            return $target->fresh();
        });
    }

    public function resetPassword(User $admin, User $target, string $password): void
    {
        $target->update([
            'password' => $password,
            'must_change_password' => true,
        ]);
        $this->sessions->revokeAllSessions($target);
        $this->auditLog->log($admin, $target, 'password_reset');
    }

    public function revokeSessions(User $admin, User $target): void
    {
        $this->sessions->revokeAllSessions($target);
        $this->auditLog->log($admin, $target, 'user_sessions_revoked');
    }

    public function delete(User $admin, User $target): void
    {
        if ($admin->is($target)) {
            throw ValidationException::withMessages([
                'user' => 'Du kannst dein aktuell angemeldetes Konto nicht loeschen.',
            ]);
        }

        $this->protectLastAdmin($target, $target->role->value, $target->status->value, true);

        $this->sessions->revokeAllSessions($target);
        $target->update(['status' => UserStatus::Inactive]);
        $target->delete();
        $this->auditLog->log($admin, $target, 'user_deleted');
    }

    private function protectLastAdmin(User $target, string $newRole, string $newStatus, bool $isDeleting): void
    {
        if ($target->role !== UserRole::Admin || $target->status !== UserStatus::Active || $target->trashed()) {
            return;
        }

        $wouldRemoveAdmin = $isDeleting || $newRole !== UserRole::Admin->value || $newStatus !== UserStatus::Active->value;

        if (! $wouldRemoveAdmin) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->whereKeyNot($target->id)
            ->where('role', UserRole::Admin->value)
            ->where('status', UserStatus::Active->value)
            ->count();

        if ($otherActiveAdmins === 0) {
            throw ValidationException::withMessages([
                'role' => 'Mindestens ein aktiver Administrator muss immer bestehen bleiben.',
            ]);
        }
    }
}
