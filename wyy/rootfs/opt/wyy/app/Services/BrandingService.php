<?php

namespace App\Services;

use App\Models\BrandingAsset;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrandingService
{
    private const CACHE_KEY = 'branding.payload';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly CacheRepository $cache,
    ) {}

    public function defaults(): array
    {
        return [
            'general' => [
                'app_name' => config('app.name', 'Weinassistent'),
                'short_name' => 'Wein',
                'subtitle' => config('app.subtitle', 'Meine persoenliche Weinwelt'),
                'description' => config('app.description', 'Persoenlicher Wein-Kompass fuer Alltag, Laden und Restaurant.'),
                'copyright_text' => '© 2026 '.config('app.name', 'Weinassistent'),
                'operator_name' => null,
                'website_url' => null,
            ],
            'login' => [
                'background_enabled' => false,
                'background_color' => '#120d0d',
                'overlay_enabled' => true,
                'overlay_color' => '#120d0d',
                'overlay_strength' => 48,
                'box_background_color' => '#201615',
                'box_opacity' => 84,
                'box_width' => 'standard',
                'layout' => 'centered',
                'background_fit' => 'cover',
                'background_position' => 'center',
                'logo_width' => 180,
                'logo_alignment' => 'center',
                'title' => 'Willkommen beim Weinassistenten',
                'subtitle' => 'Deine persoenliche Weinwelt',
                'welcome_text' => 'Melde dich an, um Scans, Sammlung und Empfehlungen in deiner eigenen Weinwelt zu nutzen.',
                'button_text' => 'Anmelden',
                'forgot_password_text' => 'Passwort vergessen',
                'footer_text' => 'Sicherer Zugriff auf deine persoenliche Weinwelt',
                'box_radius' => 'rounded',
                'box_shadow' => 'soft',
            ],
            'colors' => [
                'primary' => '#d1a15f',
                'secondary' => '#6f2e2a',
                'accent' => '#f0c985',
                'background' => '#120d0d',
                'surface' => '#201615',
                'text' => '#f7f0e6',
                'text_muted' => '#cabfb8',
                'navigation' => '#110c0c',
                'navigation_active' => '#f0c985',
                'button_primary' => '#d1a15f',
                'button_text' => '#221614',
                'login_box_background' => '#201615',
            ],
            'icons' => [
                'logo_display' => 'main_logo',
            ],
            'pwa' => [
                'pwa_name' => null,
                'pwa_short_name' => null,
                'pwa_description' => null,
                'theme_color' => '#201615',
                'background_color' => '#120d0d',
            ],
        ];
    }

    public function valuesForSection(string $section): array
    {
        if ($section === 'preview') {
            return [];
        }

        $defaults = $this->defaults()[$section] ?? [];
        $values = $this->settings->group('branding.'.$section);

        return array_merge($defaults, $values);
    }

    public function viewData(): array
    {
        $general = $this->valuesForSection('general');
        $login = $this->valuesForSection('login');
        $colors = $this->valuesForSection('colors');
        $icons = $this->valuesForSection('icons');
        $pwa = $this->valuesForSection('pwa');
        $version = $this->version();

        return [
            'general' => $general,
            'login' => $login,
            'colors' => $colors,
            'icons' => [
                'app_logo' => match ($icons['logo_display'] ?? 'main_logo') {
                    'login_logo' => $this->assetUrl('login_logo', $version) ?: $this->assetUrl('main_logo', $version),
                    'admin_logo' => $this->assetUrl('admin_logo', $version) ?: $this->assetUrl('main_logo', $version),
                    default => $this->assetUrl('main_logo', $version),
                },
                'main_logo' => $this->assetUrl('main_logo', $version),
                'login_logo' => $this->assetUrl('login_logo', $version) ?: $this->assetUrl('main_logo', $version),
                'admin_logo' => $this->assetUrl('admin_logo', $version) ?: $this->assetUrl('main_logo', $version),
                'favicon' => route('branding.favicon', ['v' => $version]),
                'apple_touch_icon' => route('branding.apple-touch-icon', ['v' => $version]),
                'pwa_icon_192' => route('branding.asset', ['slot' => 'pwa_icon_192', 'v' => $version]),
                'pwa_icon_512' => route('branding.asset', ['slot' => 'pwa_icon_512', 'v' => $version]),
                'pwa_maskable_icon' => route('branding.asset', ['slot' => 'pwa_maskable_icon', 'v' => $version]),
                'login_background' => $this->assetUrl('login_background', $version),
                'logo_display' => $icons['logo_display'] ?? 'main_logo',
            ],
            'pwa' => [
                'name' => $pwa['pwa_name'] ?: $general['app_name'],
                'short_name' => $pwa['pwa_short_name'] ?: ($general['short_name'] ?: Str::limit($general['app_name'], 12, '')),
                'description' => $pwa['pwa_description'] ?: $general['description'],
                'theme_color' => $pwa['theme_color'],
                'background_color' => $pwa['background_color'],
            ],
            'login_styles' => [
                'overlay_rgba' => $this->hexToRgba($login['overlay_color'], ((int) $login['overlay_strength']) / 100),
                'box_rgba' => $this->hexToRgba($login['box_background_color'], ((int) $login['box_opacity']) / 100),
            ],
            'version' => $version,
            'css_variables' => $this->cssVariables($colors, $login),
        ];
    }

    public function publicAssetForSlot(string $slot): array
    {
        $asset = $this->assetForSlot($slot);

        if ($asset && Storage::disk('local')->exists($asset->storage_path)) {
            return [$asset->storage_path, $asset->mime_type];
        }

        return match ($slot) {
            'favicon' => [public_path('icon-192.png'), 'image/png'],
            'apple_touch_icon' => [public_path('apple-touch-icon.png'), 'image/png'],
            'pwa_icon_192' => [public_path('icon-192.png'), 'image/png'],
            'pwa_icon_512', 'pwa_maskable_icon' => [public_path('icon-512.png'), 'image/png'],
            default => [public_path('icon-192.png'), 'image/png'],
        };
    }

    public function storeSection(string $section, array $data, User $admin): string
    {
        if ($section === 'preview') {
            return 'branding_preview_opened';
        }

        if (in_array($section, ['login', 'logos', 'icons', 'pwa'], true)) {
            $this->handleAssetUploads($section, $data, $admin);
        }

        $persist = Arr::except($data, [
            'main_logo', 'login_logo', 'admin_logo', 'favicon', 'apple_touch_icon', 'app_icon', 'maskable_icon',
            'login_background',
        ]);

        if ($persist !== []) {
            $this->settings->setMany('branding.'.$section, $this->typedEntries($persist));
        }

        $this->flushCache();

        return match ($section) {
            'general' => 'branding_general_updated',
            'login' => 'branding_login_updated',
            'logos' => 'branding_logo_updated',
            'colors' => 'branding_colors_updated',
            'icons' => 'branding_icons_updated',
            'pwa' => 'branding_pwa_updated',
            default => 'branding_updated',
        };
    }

    public function resetSection(string $section, User $admin): string
    {
        if (! array_key_exists($section, $this->defaults())) {
            throw ValidationException::withMessages(['section' => 'Unbekannter Branding-Bereich.']);
        }

        foreach (array_keys($this->valuesForSection($section)) as $key) {
            $this->settings->forget('branding.'.$section, $key);
        }

        if ($section === 'logos') {
            foreach (['main_logo', 'login_logo', 'admin_logo'] as $slot) {
                $this->removeAsset($slot, $admin, false);
            }
        }

        if ($section === 'icons') {
            foreach (['favicon', 'apple_touch_icon'] as $slot) {
                $this->removeAsset($slot, $admin, false);
            }
        }

        if ($section === 'pwa') {
            foreach (['pwa_icon_192', 'pwa_icon_512', 'pwa_maskable_icon'] as $slot) {
                $this->removeAsset($slot, $admin, false);
            }
        }

        if ($section === 'login') {
            $this->removeAsset('login_background', $admin, false);
        }

        $this->flushCache();

        return 'branding_reset_'.$section;
    }

    public function resetAll(User $admin): void
    {
        foreach (array_keys($this->defaults()) as $section) {
            $this->resetSection($section, $admin);
        }
    }

    public function removeAsset(string $slot, User $admin, bool $flush = true): string
    {
        $asset = $this->assetForSlot($slot);

        if ($asset && Storage::disk('local')->exists($asset->storage_path)) {
            Storage::disk('local')->delete($asset->storage_path);
        }

        if ($asset) {
            $asset->delete();
        }

        $this->settings->forget('branding.assets', $slot);

        if ($flush) {
            $this->flushCache();
        }

        return 'branding_asset_removed';
    }

    public function contrastWarnings(): array
    {
        $colors = $this->valuesForSection('colors');
        $warnings = [];

        if ($this->contrastRatio($colors['text'], $colors['background']) < 4.5) {
            $warnings[] = 'Textfarbe und Hintergrund koennen schwer lesbar sein.';
        }

        if ($this->contrastRatio($colors['button_text'], $colors['button_primary']) < 4.5) {
            $warnings[] = 'Button-Text und Primaerbutton haben moeglicherweise zu wenig Kontrast.';
        }

        return $warnings;
    }

    public function flushCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
        foreach (['branding.general', 'branding.login', 'branding.colors', 'branding.icons', 'branding.pwa', 'branding.assets'] as $group) {
            $this->settings->flushGroup($group);
        }
    }

    public function assetForSlot(string $slot): ?BrandingAsset
    {
        if (! Schema::hasTable('branding_assets')) {
            return null;
        }

        $assetId = $this->settings->get('branding.assets', $slot);

        return $assetId ? BrandingAsset::query()->find($assetId) : null;
    }

    private function assetUrl(string $slot, string $version): ?string
    {
        if (in_array($slot, ['main_logo', 'login_logo', 'admin_logo', 'login_background'], true)) {
            $asset = $this->assetForSlot($slot);

            if (! $asset || ! Storage::disk('local')->exists($asset->storage_path)) {
                return null;
            }
        }

        return route('branding.asset', ['slot' => $slot, 'v' => $version]);
    }

    private function handleAssetUploads(string $section, array $data, User $admin): void
    {
        if ($section === 'logos') {
            foreach (['main_logo', 'login_logo', 'admin_logo'] as $slot) {
                if (($data[$slot] ?? null) instanceof UploadedFile) {
                    $this->storeRasterAsset($data[$slot], $slot, $admin, 1800);
                }
            }

            return;
        }

        if ($section === 'icons') {
            if (($data['favicon'] ?? null) instanceof UploadedFile) {
                $this->storeRasterAsset($data['favicon'], 'favicon', $admin, 48, ['width' => 48, 'height' => 48], 'png');
            }

            if (($data['apple_touch_icon'] ?? null) instanceof UploadedFile) {
                $this->storeRasterAsset($data['apple_touch_icon'], 'apple_touch_icon', $admin, 180, ['width' => 180, 'height' => 180], 'png');
            }

            return;
        }

        if ($section === 'pwa') {
            if (($data['app_icon'] ?? null) instanceof UploadedFile) {
                $this->storeRasterAsset($data['app_icon'], 'pwa_icon_192', $admin, 192, ['width' => 192, 'height' => 192], 'png');
                $this->storeRasterAsset($data['app_icon'], 'pwa_icon_512', $admin, 512, ['width' => 512, 'height' => 512], 'png');
                if (! $this->assetForSlot('apple_touch_icon')) {
                    $this->storeRasterAsset($data['app_icon'], 'apple_touch_icon', $admin, 180, ['width' => 180, 'height' => 180], 'png');
                }
                if (! $this->assetForSlot('favicon')) {
                    $this->storeRasterAsset($data['app_icon'], 'favicon', $admin, 48, ['width' => 48, 'height' => 48], 'png');
                }
            }

            if (($data['maskable_icon'] ?? null) instanceof UploadedFile) {
                $this->storeRasterAsset($data['maskable_icon'], 'pwa_maskable_icon', $admin, 512, ['width' => 512, 'height' => 512], 'png');
            }

            return;
        }

        if ($section === 'login' && ($data['login_background'] ?? null) instanceof UploadedFile) {
            $this->storeRasterAsset($data['login_background'], 'login_background', $admin, 2200, null, 'jpg');
        }
    }

    private function typedEntries(array $values): array
    {
        $entries = [];

        foreach ($values as $key => $value) {
            $entries[$key] = [
                'value' => $value,
                'type' => is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string'),
                'encrypted' => false,
            ];
        }

        return $entries;
    }

    private function cssVariables(array $colors, array $login): array
    {
        return [
            '--color-primary' => $colors['primary'],
            '--color-secondary' => $colors['secondary'],
            '--color-accent' => $colors['accent'],
            '--color-background' => $colors['background'],
            '--color-surface' => $colors['surface'],
            '--color-text' => $colors['text'],
            '--color-text-muted' => $colors['text_muted'],
            '--color-navigation' => $colors['navigation'],
            '--color-navigation-active' => $colors['navigation_active'],
            '--color-button-primary' => $colors['button_primary'],
            '--color-button-text' => $colors['button_text'],
            '--color-login-box-background' => $colors['login_box_background'],
            '--color-ink' => $colors['background'],
            '--color-cream' => $colors['text'],
            '--color-mist' => $colors['text_muted'],
            '--color-gold' => $colors['primary'],
            '--color-burgundy-soft' => $this->hexToRgba($colors['secondary'], 0.36),
        ];
    }

    private function version(): string
    {
        $parts = [];

        foreach (['branding.general', 'branding.login', 'branding.colors', 'branding.icons', 'branding.pwa', 'branding.assets'] as $group) {
            $parts[] = json_encode($this->settings->group($group));
        }

        if (Schema::hasTable('branding_assets')) {
            $parts[] = (string) optional(BrandingAsset::query()->latest('updated_at')->first())->updated_at;
        }

        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    private function storeRasterAsset(
        UploadedFile $file,
        string $slot,
        User $admin,
        int $targetMax,
        ?array $forceSize = null,
        string $format = 'png',
    ): BrandingAsset {
        $raw = @file_get_contents($file->getRealPath());
        $image = $raw !== false && function_exists('imagecreatefromstring') ? @imagecreatefromstring($raw) : false;

        if (! $image) {
            throw ValidationException::withMessages([$slot => 'Die Bilddatei konnte nicht verarbeitet werden.']);
        }

        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);

        if ($forceSize) {
            $cropSize = min($sourceWidth, $sourceHeight);
            $cropX = max(0, (int) floor(($sourceWidth - $cropSize) / 2));
            $cropY = max(0, (int) floor(($sourceHeight - $cropSize) / 2));
            $targetWidth = $forceSize['width'];
            $targetHeight = $forceSize['height'];
        } else {
            $scale = $targetMax > 0 && max($sourceWidth, $sourceHeight) > $targetMax ? $targetMax / max($sourceWidth, $sourceHeight) : 1;
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($format === 'png') {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);
        } else {
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        }

        if ($forceSize) {
            imagecopyresampled($canvas, $image, 0, 0, $cropX, $cropY, $targetWidth, $targetHeight, $cropSize, $cropSize);
        } else {
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        }

        $directory = 'branding/'.$slot;
        $filename = Str::uuid()->toString().'.'.$format;
        $path = $directory.'/'.$filename;

        ob_start();
        if ($format === 'png') {
            imagepng($canvas, null, 8);
            $mime = 'image/png';
        } else {
            imagejpeg($canvas, null, 84);
            $mime = 'image/jpeg';
        }
        $binary = (string) ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($image);

        Storage::disk('local')->put($path, $binary);

        $oldAsset = $this->assetForSlot($slot);
        $asset = BrandingAsset::query()->create([
            'type' => $slot,
            'filename' => $filename,
            'storage_path' => $path,
            'mime_type' => $mime,
            'width' => $targetWidth,
            'height' => $targetHeight,
            'file_size' => strlen($binary),
            'created_by' => $admin->id,
        ]);

        $this->settings->set('branding.assets', $slot, $asset->id, 'int');

        if ($oldAsset) {
            $oldPath = $oldAsset->storage_path;
            $oldAsset->delete();

            if ($oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
                Storage::disk('local')->delete($oldPath);
            }
        }

        return $asset;
    }

    private function hexToRgba(string $hex, float $opacity): string
    {
        [$r, $g, $b] = $this->hexToRgb($hex);

        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $opacity);
    }

    private function contrastRatio(string $hexA, string $hexB): float
    {
        $l1 = $this->relativeLuminance($hexA);
        $l2 = $this->relativeLuminance($hexB);
        $lighter = max($l1, $l2);
        $darker = min($l1, $l2);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->hexToRgb($hex);
        $channels = array_map(fn (int $channel) => $channel / 255, [$r, $g, $b]);
        $channels = array_map(function (float $channel): float {
            return $channel <= 0.03928 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, $channels);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private function hexToRgb(string $hex): array
    {
        $value = ltrim($hex, '#');

        return [
            hexdec(substr($value, 0, 2)),
            hexdec(substr($value, 2, 2)),
            hexdec(substr($value, 4, 2)),
        ];
    }
}
