<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin', function () {
    $name = $this->ask('Name des Administrators');
    $email = $this->ask('E-Mail-Adresse');
    $password = $this->secret('Passwort');

    if (! $name || ! $email || ! $password) {
        $this->error('Name, E-Mail und Passwort sind erforderlich.');

        return self::FAILURE;
    }

    if (User::query()->where('email', $email)->exists()) {
        $this->error('Ein Benutzer mit dieser E-Mail existiert bereits.');

        return self::FAILURE;
    }

    User::query()->create([
        'name' => $name,
        'email' => $email,
        'password' => Hash::make($password),
        'timezone' => 'Europe/Zurich',
        'role' => UserRole::Admin,
        'status' => UserStatus::Active,
    ]);

    $this->info('Administrator erfolgreich erstellt.');

    return self::SUCCESS;
})->purpose('Creates an initial administrator account');

Artisan::command('app:build-release {target?}', function (?string $target = null) {
    $filesystem = new Filesystem;
    $target ??= base_path('dist/wein-app-shared-hosting.zip');
    $targetDirectory = dirname($target);

    if (! class_exists(ZipArchive::class)) {
        $this->error('Die PHP ZIP Extension wird fuer den Release-Build benoetigt.');

        return self::FAILURE;
    }

    $filesystem->ensureDirectoryExists($targetDirectory);

    $zip = new ZipArchive;

    if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        $this->error('Release ZIP konnte nicht erstellt werden.');

        return self::FAILURE;
    }

    $exclude = [
        '.env',
        '.git',
        '.gitignore',
        'node_modules',
        'tests',
        'database/database.sqlite',
        'storage/logs',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/testing',
        'storage/framework/views',
        '.phpunit.result.cache',
        '.DS_Store',
        'dist',
    ];

    $base = base_path();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        $pathname = $file->getPathname();
        $relative = ltrim(str_replace($base, '', $pathname), DIRECTORY_SEPARATOR);
        $normalized = str_replace('\\', '/', $relative);

        if ($normalized === '') {
            continue;
        }

        if (collect($exclude)->contains(fn (string $prefix) => $normalized === $prefix || str_starts_with($normalized, rtrim($prefix, '/').'/'))) {
            continue;
        }

        if ($file->isDir()) {
            $zip->addEmptyDir($normalized);

            continue;
        }

        $zip->addFile($pathname, $normalized);
    }

    $zip->close();

    $this->info('Release ZIP erstellt: '.$target);

    return self::SUCCESS;
})->purpose('Builds a shared-hosting release ZIP with vendor and production assets');
