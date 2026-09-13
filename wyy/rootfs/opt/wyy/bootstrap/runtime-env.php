<?php

if (! function_exists('wein_app_set_env')) {
    function wein_app_set_env(string $key, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$runtimeConfigFile = dirname(__DIR__).'/storage/app/config/runtime.php';
$fallbackRuntimeConfigFile = dirname(__DIR__).'/bootstrap/cache/wein-runtime.php';
$lockFile = dirname(__DIR__).'/storage/app/installed.lock';
$fallbackLockFile = dirname(__DIR__).'/bootstrap/cache/wein-installed.lock';

// Runtime configuration is authoritative when the installer cannot update an existing .env.
if (file_exists($runtimeConfigFile)) {
    $runtimeConfig = require $runtimeConfigFile;

    if (is_array($runtimeConfig)) {
        foreach ($runtimeConfig as $key => $value) {
            wein_app_set_env((string) $key, is_scalar($value) ? (string) $value : null);
        }
    }
}

if (! getenv('DB_DATABASE') && file_exists($fallbackRuntimeConfigFile)) {
    $runtimeConfig = require $fallbackRuntimeConfigFile;

    if (is_array($runtimeConfig)) {
        foreach ($runtimeConfig as $key => $value) {
            wein_app_set_env((string) $key, is_scalar($value) ? (string) $value : null);
        }
    }
}

if (! getenv('DB_DATABASE') && file_exists($lockFile)) {
    $lock = json_decode((string) file_get_contents($lockFile), true);

    if (is_array($lock) && is_array($lock['environment'] ?? null)) {
        foreach ($lock['environment'] as $key => $value) {
            wein_app_set_env((string) $key, is_scalar($value) ? (string) $value : null);
        }
    }
}

if (! getenv('DB_DATABASE') && file_exists($fallbackLockFile)) {
    $lock = json_decode((string) file_get_contents($fallbackLockFile), true);

    if (is_array($lock) && is_array($lock['environment'] ?? null)) {
        foreach ($lock['environment'] as $key => $value) {
            wein_app_set_env((string) $key, is_scalar($value) ? (string) $value : null);
        }
    }
}

if (! getenv('APP_KEY')) {
    $temporaryKey = 'base64:'.base64_encode(substr(hash('sha256', dirname(__DIR__), true), 0, 32));
    wein_app_set_env('APP_KEY', $temporaryKey);
}
