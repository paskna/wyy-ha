<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PostInstallationValidator
{
    public function errors(): array
    {
        $errors = [];

        if (! is_string(config('app.key')) || trim((string) config('app.key')) === '') {
            $errors[] = 'APP_KEY fehlt.';
        }

        try {
            DB::connection()->getPdo();

            if (! Schema::hasTable('migrations')) {
                $errors[] = 'Die Migrationstabelle fehlt.';
            }

            if (! Schema::hasTable('users')) {
                $errors[] = 'Die Benutzertabelle fehlt.';
            } elseif (! User::query()->where('role', UserRole::Admin->value)->where('status', UserStatus::Active->value)->exists()) {
                $errors[] = 'Es wurde kein aktiver Administrator erstellt.';
            }

            $migrator = app('migrator');
            $pending = array_diff(
                array_keys($migrator->getMigrationFiles([database_path('migrations')])),
                $migrator->getRepository()->getRan(),
            );

            if ($pending !== []) {
                $errors[] = 'Ausstehende Migrationen: '.implode(', ', $pending).'.';
            }
        } catch (\Throwable) {
            $errors[] = 'Die Datenbankverbindung konnte nach der Installation nicht geprueft werden.';
        }

        if (! Route::has('login')) {
            $errors[] = 'Die Login-Route ist nicht registriert.';
        }

        foreach ([
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $path) {
            if (! File::isDirectory($path) || ! is_writable($path)) {
                $errors[] = 'Verzeichnis nicht beschreibbar: '.$path;
            }
        }

        return $errors;
    }

    public function assertValid(): void
    {
        $errors = $this->errors();

        if ($errors !== []) {
            throw new \RuntimeException('Post-Installationspruefung fehlgeschlagen: '.implode(' ', $errors));
        }
    }
}
