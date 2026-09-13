<?php

namespace App\Services;

use Illuminate\Support\Str;

class WineNormalizationService
{
    public function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = mb_strtolower($value);
        $value = str_replace(['’', '`', '´'], "'", $value);
        $value = str_replace(['–', '—', '_'], '-', $value);
        $value = preg_replace('/[^\pL\pN\s\'-]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return Str::of($value)->ascii()->replaceMatches('/\s+/', ' ')->trim()->value();
    }

    public function vintage(?string $value): ?int
    {
        if ($value === null || $value === '' || strtolower($value) === 'nv') {
            return null;
        }

        preg_match('/(19|20)\d{2}/', $value, $matches);

        return isset($matches[0]) ? (int) $matches[0] : null;
    }
}
