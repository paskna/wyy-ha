<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\BrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminBrandingController extends Controller
{
    public function __construct(
        private readonly BrandingService $branding,
        private readonly AuditLogService $audit,
    ) {}

    public function edit(string $section = 'general'): View
    {
        return view('admin.branding.edit', [
            'section' => $section,
            'sections' => $this->sections(),
            'values' => $this->branding->valuesForSection($section),
            'branding' => $this->branding->viewData(),
            'contrastWarnings' => $section === 'colors' ? $this->branding->contrastWarnings() : [],
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        $data = match ($section) {
            'general' => $request->validate([
                'app_name' => ['required', 'string', 'max:255'],
                'short_name' => ['nullable', 'string', 'max:80'],
                'subtitle' => ['nullable', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:500'],
                'copyright_text' => ['nullable', 'string', 'max:255'],
                'operator_name' => ['nullable', 'string', 'max:255'],
                'website_url' => ['nullable', 'url', 'max:255'],
            ]),
            'login' => $this->normalizeBooleans($request, $request->validate([
                'login_background' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:15360'],
                'background_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'overlay_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'overlay_strength' => ['required', 'integer', 'min:0', 'max:80'],
                'box_background_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'box_opacity' => ['required', 'integer', 'min:40', 'max:100'],
                'box_width' => ['required', Rule::in(['compact', 'standard', 'wide'])],
                'layout' => ['required', Rule::in(['centered', 'left', 'right', 'split-left', 'split-right'])],
                'background_fit' => ['required', Rule::in(['cover', 'contain', 'original'])],
                'background_position' => ['required', Rule::in(['center', 'top', 'bottom', 'left', 'right'])],
                'logo_width' => ['required', 'integer', 'min:80', 'max:480'],
                'logo_alignment' => ['required', Rule::in(['left', 'center', 'right'])],
                'title' => ['nullable', 'string', 'max:255'],
                'subtitle' => ['nullable', 'string', 'max:255'],
                'welcome_text' => ['nullable', 'string', 'max:500'],
                'button_text' => ['nullable', 'string', 'max:80'],
                'forgot_password_text' => ['nullable', 'string', 'max:120'],
                'footer_text' => ['nullable', 'string', 'max:255'],
                'box_radius' => ['required', Rule::in(['soft', 'rounded', 'pill'])],
                'box_shadow' => ['required', Rule::in(['none', 'soft', 'medium'])],
            ]), ['overlay_enabled', 'background_enabled']),
            'logos' => $request->validate([
                'logo_display' => ['required', Rule::in(['main_logo', 'login_logo', 'admin_logo'])],
                'main_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                'login_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                'admin_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            ]),
            'colors' => $request->validate([
                'primary' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'secondary' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'accent' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'background' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'surface' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'text' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'text_muted' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'navigation' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'navigation_active' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'button_primary' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'button_text' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'login_box_background' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
            ]),
            'icons' => $request->validate([
                'favicon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                'apple_touch_icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            ]),
            'pwa' => $request->validate([
                'pwa_name' => ['nullable', 'string', 'max:255'],
                'pwa_short_name' => ['nullable', 'string', 'max:40'],
                'pwa_description' => ['nullable', 'string', 'max:500'],
                'theme_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'background_color' => ['required', 'regex:/^#[A-Fa-f0-9]{6}$/'],
                'app_icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
                'maskable_icon' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            ]),
            default => abort(404),
        };

        $action = $this->branding->storeSection($section, $data, $request->user());
        $this->audit->log($request->user(), $request->user(), $action, ['section' => $section]);

        return back()->with('status', 'Branding gespeichert.');
    }

    public function reset(Request $request, string $section): RedirectResponse
    {
        $action = $this->branding->resetSection($section, $request->user());
        $this->audit->log($request->user(), $request->user(), $action, ['section' => $section]);

        return back()->with('status', 'Bereich auf Standard zurueckgesetzt.');
    }

    public function resetAll(Request $request): RedirectResponse
    {
        $this->branding->resetAll($request->user());
        $this->audit->log($request->user(), $request->user(), 'branding_reset_all');

        return back()->with('status', 'Gesamtes Branding auf Standard zurueckgesetzt.');
    }

    public function destroyAsset(Request $request, string $slot): RedirectResponse
    {
        $action = $this->branding->removeAsset($slot, $request->user());
        $this->audit->log($request->user(), $request->user(), $action, ['slot' => $slot]);

        return back()->with('status', 'Asset entfernt. Es wird wieder der Standard verwendet.');
    }

    private function sections(): array
    {
        return [
            'general' => 'Allgemein',
            'login' => 'Loginseite',
            'logos' => 'Logos',
            'colors' => 'Farben',
            'icons' => 'Favicon & Icons',
            'pwa' => 'PWA',
            'preview' => 'Vorschau',
        ];
    }

    private function normalizeBooleans(Request $request, array $data, array $keys): array
    {
        foreach ($keys as $key) {
            $data[$key] = $request->boolean($key);
        }

        return $data;
    }
}
