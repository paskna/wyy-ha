<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SettingsService
{
    public function __construct(private readonly CacheRepository $cache) {}

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Arr::get($this->group($group), $key, $default);
    }

    public function getWithFallback(string $group, string $key, mixed $envFallback = null, mixed $default = null): mixed
    {
        $value = $this->get($group, $key);

        if ($value !== null && $value !== '') {
            return $value;
        }

        if ($envFallback !== null && $envFallback !== '') {
            return $envFallback;
        }

        return $default;
    }

    public function set(string $group, string $key, mixed $value, string $type = 'string', bool $encrypted = false): Setting
    {
        $this->ensureTableExists();

        $storedValue = $this->prepareForStorage($value, $type, $encrypted);

        $setting = Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'is_encrypted' => $encrypted,
            ],
        );

        $this->flushGroup($group);

        return $setting;
    }

    public function setMany(string $group, array $entries): void
    {
        if (! $this->tableExists()) {
            return;
        }

        foreach ($entries as $key => $entry) {
            $value = is_array($entry) ? ($entry['value'] ?? null) : $entry;
            $type = is_array($entry) ? ($entry['type'] ?? 'string') : $this->detectType($entry);
            $encrypted = is_array($entry) ? (bool) ($entry['encrypted'] ?? false) : false;

            Setting::query()->updateOrCreate(
                ['group' => $group, 'key' => $key],
                [
                    'value' => $this->prepareForStorage($value, $type, $encrypted),
                    'type' => $type,
                    'is_encrypted' => $encrypted,
                ],
            );
        }

        $this->flushGroup($group);
    }

    public function forget(string $group, string $key): void
    {
        if (! $this->tableExists()) {
            return;
        }

        Setting::query()->where('group', $group)->where('key', $key)->delete();
        $this->flushGroup($group);
    }

    public function group(string $group): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return $this->cache->rememberForever($this->cacheKey($group), function () use ($group): array {
            return Setting::query()
                ->where('group', $group)
                ->get()
                ->mapWithKeys(fn (Setting $setting) => [$setting->key => $this->decode($setting)])
                ->all();
        });
    }

    public function flushGroup(string $group): void
    {
        $this->cache->forget($this->cacheKey($group));
    }

    private function cacheKey(string $group): string
    {
        return 'settings.group.'.$group;
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('settings');
    }

    private function ensureTableExists(): void
    {
        if (! $this->tableExists()) {
            throw new \RuntimeException('Settings table is not available.');
        }
    }

    private function decode(Setting $setting): mixed
    {
        $value = $setting->value;

        if ($value === null) {
            return null;
        }

        if ($setting->is_encrypted) {
            $value = Crypt::decryptString($value);
        }

        return match ($setting->type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    private function prepareForStorage(mixed $value, string $type, bool $encrypted): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_THROW_ON_ERROR),
            default => (string) $value,
        };

        return $encrypted ? Crypt::encryptString($normalized) : $normalized;
    }

    private function detectType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}
