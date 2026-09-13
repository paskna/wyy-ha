<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PwaPersistentLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_request_with_valid_remember_cookie_redirects_directly_to_dashboard(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email' => 'remember-root@example.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $rememberCookie = $loginResponse->getCookie(Auth::guard()->getRecallerName());

        $this->app['session']->flush();
        $this->app['auth']->forgetGuards();

        $response = $this
            ->withCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->withCookie(config('session.cookie'), 'expired-session')
            ->get(route('home'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_root_request_without_auth_redirects_to_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_logout_clears_persistent_login_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'persist@example.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $rememberCookie = $loginResponse->getCookie(Auth::guard()->getRecallerName());

        $this->withCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertCookieExpired(Auth::guard()->getRecallerName());
    }

    public function test_deactivated_user_with_old_remember_cookie_is_rejected(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'email' => 'inactive-remember@example.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $rememberCookie = $loginResponse->getCookie(Auth::guard()->getRecallerName());

        $user->update(['status' => UserStatus::Inactive]);

        Auth::logout();
        $this->app['session']->flush();

        $this->withCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->withCookie(config('session.cookie'), 'expired-session')
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_deleted_user_with_old_remember_cookie_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'deleted-remember@example.test',
            'password' => 'password',
        ]);

        $loginResponse = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'));

        $rememberCookie = $loginResponse->getCookie(Auth::guard()->getRecallerName());

        $user->delete();

        Auth::logout();
        $this->app['session']->flush();

        $this->withCookie($rememberCookie->getName(), $rememberCookie->getValue())
            ->withCookie(config('session.cookie'), 'expired-session')
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_remember_cookie_uses_subfolder_path(): void
    {
        $user = User::factory()->create([
            'email' => 'subfolder-remember@example.test',
            'password' => 'password',
        ]);

        $response = $this->withServerVariables([
            'HTTP_HOST' => 'example.test',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/visualiz/wyy/index.php',
            'PHP_SELF' => '/visualiz/wyy/index.php',
            'REQUEST_URI' => '/visualiz/wyy/login',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ]);

        $cookies = $response->headers->getCookies();
        $rememberCookie = collect($cookies)->first(fn ($cookie) => $cookie->getName() === Auth::guard()->getRecallerName());

        $this->assertNotNull($rememberCookie);
        $this->assertSame('/visualiz/wyy', $rememberCookie->getPath());
    }
}
