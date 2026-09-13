<?php

use App\Http\Controllers\Admin\AdminApiActivityController;
use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminBrandingController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminIntegrationController;
use App\Http\Controllers\Admin\AdminSettingsController;
use App\Http\Controllers\Admin\AdminSystemController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWineDataController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BrandingAssetController;
use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeAssistantSetupController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\MoreController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\TopWineController;
use App\Http\Controllers\TasteProfileController;
use App\Http\Controllers\WineController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Auth::check() ? redirect()->route('dashboard') : redirect()->route('login'))->name('home');
Route::get('/health', HealthController::class)->name('health');
Route::get('/manifest.json', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'worker'])->name('pwa.worker');
Route::get('/favicon.ico', [BrandingAssetController::class, 'show'])->defaults('slot', 'favicon')->name('branding.favicon');
Route::get('/apple-touch-icon.png', [BrandingAssetController::class, 'show'])->defaults('slot', 'apple_touch_icon')->name('branding.apple-touch-icon');
Route::get('/icon-192.png', [BrandingAssetController::class, 'show'])->defaults('slot', 'pwa_icon_192')->name('branding.icon-192');
Route::get('/icon-512.png', [BrandingAssetController::class, 'show'])->defaults('slot', 'pwa_icon_512')->name('branding.icon-512');
Route::get('/branding/assets/{slot}', [BrandingAssetController::class, 'show'])->name('branding.asset');

Route::prefix('install')->name('install.')->group(function (): void {
    Route::get('/', [InstallController::class, 'welcome'])->name('welcome');
    Route::get('/system', [InstallController::class, 'system'])->name('system');
    Route::get('/url', [InstallController::class, 'url'])->name('url');
    Route::post('/url', [InstallController::class, 'storeUrl'])->name('url.store');
    Route::get('/datenbank', [InstallController::class, 'database'])->name('database');
    Route::post('/datenbank', [InstallController::class, 'storeDatabase'])->name('database.store');
    Route::get('/administrator', [InstallController::class, 'admin'])->name('admin');
    Route::post('/administrator', [InstallController::class, 'storeAdmin'])->name('admin.store');
    Route::get('/einstellungen', [InstallController::class, 'settings'])->name('settings');
    Route::post('/einstellungen', [InstallController::class, 'storeSettings'])->name('settings.store');
    Route::get('/ausfuehren', [InstallController::class, 'run'])->name('run');
    Route::post('/ausfuehren', [InstallController::class, 'perform'])->name('perform');
    Route::get('/fertig', [InstallController::class, 'complete'])->name('complete');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/setup', [HomeAssistantSetupController::class, 'create'])->name('ha.setup.create');
    Route::post('/setup', [HomeAssistantSetupController::class, 'store'])->name('ha.setup.store');
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::middleware(['auth', 'active-user'])->group(function (): void {
    Route::get('/start', DashboardController::class)->name('dashboard');

    Route::get('/sammlung', [CollectionController::class, 'index'])->name('collection.index');
    Route::get('/top-weine', [TopWineController::class, 'index'])->name('top-wines.index');

    Route::get('/scannen', [ScanController::class, 'create'])->name('scans.create');
    Route::post('/scannen', [ScanController::class, 'store'])->middleware('throttle:20,1')->name('scans.store');
    Route::get('/scans/{scan}', [ScanController::class, 'show'])->name('scans.show');
    Route::post('/scans/{scan}/save', [ScanController::class, 'save'])->name('scans.save');

    Route::get('/weine/{wineVintage}', [WineController::class, 'show'])->name('wines.show');
    Route::post('/weine/{wineVintage}/sammlung', [WineController::class, 'addToCollection'])->name('wines.collection.add');
    Route::patch('/weine/{wineVintage}/preference', [WineController::class, 'updatePreference'])->name('wines.preference');
    Route::patch('/weine/{wineVintage}/note', [WineController::class, 'updateNote'])->name('wines.note');
    Route::patch('/weine/{wineVintage}/quantity', [WineController::class, 'updateQuantity'])->name('wines.quantity');

    Route::get('/mehr', [MoreController::class, 'index'])->name('more.index');
    Route::get('/genussprofil', TasteProfileController::class)->name('taste-profile.show');
    Route::get('/profil', [AuthController::class, 'profile'])->name('profile.edit');
    Route::patch('/profil', [AuthController::class, 'updateProfile'])->name('profile.update');
    Route::patch('/profil/passwort', [AuthController::class, 'updatePassword'])->name('profile.password');
    Route::post('/profil/sitzungen/andere-abmelden', [AuthController::class, 'logoutOtherSessions'])->name('profile.sessions.others.destroy');
    Route::post('/profil/sitzungen/alle-abmelden', [AuthController::class, 'logoutAllSessions'])->name('profile.sessions.destroy');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/bilder/{wineImage}', ImageController::class)->name('images.show');
});

Route::middleware(['auth', 'active-user', 'admin'])->prefix('administration')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');
    Route::get('/benutzer', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/benutzer/neu', [AdminUserController::class, 'create'])->name('users.create');
    Route::post('/benutzer', [AdminUserController::class, 'store'])->name('users.store');
    Route::get('/benutzer/{user}', [AdminUserController::class, 'show'])->name('users.show');
    Route::get('/benutzer/{user}/bearbeiten', [AdminUserController::class, 'edit'])->name('users.edit');
    Route::patch('/benutzer/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::get('/benutzer/{user}/passwort', [AdminUserController::class, 'editPassword'])->name('users.password.edit');
    Route::patch('/benutzer/{user}/passwort', [AdminUserController::class, 'updatePassword'])->name('users.password.update');
    Route::patch('/benutzer/{user}/status', [AdminUserController::class, 'toggleStatus'])->name('users.status');
    Route::post('/benutzer/{user}/sitzungen/beenden', [AdminUserController::class, 'destroySessions'])->name('users.sessions.destroy');
    Route::delete('/benutzer/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

    Route::get('/integrationen', [AdminIntegrationController::class, 'index'])->name('integrations.index');
    Route::get('/integrationen/{provider}', [AdminIntegrationController::class, 'edit'])->name('integrations.edit');
    Route::put('/integrationen/{provider}', [AdminIntegrationController::class, 'update'])->name('integrations.update');
    Route::post('/integrationen/{provider}/testen', [AdminIntegrationController::class, 'test'])->name('integrations.test');
    Route::delete('/integrationen/{provider}/secret', [AdminIntegrationController::class, 'destroySecret'])->name('integrations.secret.destroy');

    Route::get('/systemeinstellungen/{section?}', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/systemeinstellungen/{section}', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::post('/systemeinstellungen/cache', [AdminSettingsController::class, 'clearCache'])->name('settings.cache.clear');
    Route::post('/systemeinstellungen/mail-test', [AdminSettingsController::class, 'testMail'])->name('settings.mail.test');

    Route::get('/systemdiagnose', [AdminSystemController::class, 'show'])->name('system.show');
    Route::get('/erscheinungsbild/{section?}', [AdminBrandingController::class, 'edit'])->name('branding.edit');
    Route::put('/erscheinungsbild/{section}', [AdminBrandingController::class, 'update'])->name('branding.update');
    Route::post('/erscheinungsbild/{section}/reset', [AdminBrandingController::class, 'reset'])->name('branding.reset');
    Route::post('/erscheinungsbild/reset-all', [AdminBrandingController::class, 'resetAll'])->name('branding.reset-all');
    Route::delete('/erscheinungsbild/assets/{slot}', [AdminBrandingController::class, 'destroyAsset'])->name('branding.assets.destroy');
    Route::get('/api-aktivitaeten', [AdminApiActivityController::class, 'index'])->name('api-activities.index');
    Route::get('/wein-daten/{section?}', [AdminWineDataController::class, 'index'])->name('wine-data.index');
    Route::get('/wein-stammdaten/{wine}/bearbeiten', [AdminWineDataController::class, 'editWine'])->name('wine-data.wines.edit');
    Route::patch('/wein-stammdaten/{wine}', [AdminWineDataController::class, 'updateWine'])->name('wine-data.wines.update');
    Route::get('/produzenten/{producer}/bearbeiten', [AdminWineDataController::class, 'editProducer'])->name('wine-data.producers.edit');
    Route::patch('/produzenten/{producer}', [AdminWineDataController::class, 'updateProducer'])->name('wine-data.producers.update');
    Route::get('/jahrgaenge/{vintage}/bearbeiten', [AdminWineDataController::class, 'editVintage'])->name('wine-data.vintages.edit');
    Route::patch('/jahrgaenge/{vintage}', [AdminWineDataController::class, 'updateVintage'])->name('wine-data.vintages.update');
    Route::get('/rebsorten/{grape}/bearbeiten', [AdminWineDataController::class, 'editGrape'])->name('wine-data.grapes.edit');
    Route::patch('/rebsorten/{grape}', [AdminWineDataController::class, 'updateGrape'])->name('wine-data.grapes.update');
    Route::post('/wein-daten/merge-vintages', [AdminWineDataController::class, 'mergeVintages'])->name('wine-data.merge');

    Route::get('/aktivitaeten', [AdminAuditController::class, 'index'])->name('audits.index');
});
