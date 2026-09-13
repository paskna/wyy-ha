<?php

namespace Tests\Feature;

use App\Enums\Preference;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Producer;
use App\Models\User;
use App\Models\UserWine;
use App\Models\Wine;
use App\Models\WineVintage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'rate@example.test',
            'password' => 'password',
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_login_with_remember_me_sets_recaller_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@example.test',
            'password' => 'password',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])->assertRedirect(route('dashboard'))
            ->assertCookie(Auth::guard()->getRecallerName());
    }

    public function test_normal_user_cannot_send_direct_admin_patch_request(): void
    {
        $user = User::factory()->create(['role' => UserRole::User, 'status' => UserStatus::Active]);

        $this->actingAs($user)->put(route('admin.settings.update', 'general'), [
            'app_name' => 'Verboten',
            'app_subtitle' => 'Nope',
            'language' => 'de',
            'timezone' => 'Europe/Zurich',
            'date_format' => 'd.m.Y',
            'per_page' => 12,
            'default_wine_view' => 'cards',
        ])->assertForbidden();
    }

    public function test_personal_note_is_escaped_in_html_output(): void
    {
        $user = User::factory()->create();
        $producer = Producer::query()->create(['name' => 'XSS Producer', 'normalized_name' => 'xss producer']);
        $wine = Wine::query()->create([
            'producer_id' => $producer->id,
            'name' => 'XSS Wine',
            'normalized_name' => 'xss wine',
        ]);
        $vintage = WineVintage::query()->create([
            'wine_id' => $wine->id,
            'vintage' => '2021',
        ]);

        UserWine::query()->create([
            'user_id' => $user->id,
            'wine_vintage_id' => $vintage->id,
            'preference' => Preference::Top,
            'personal_note' => '<script>alert(1)</script>',
        ]);

        $response = $this->actingAs($user)->get(route('collection.index'));

        $response->assertOk();
        $response->assertDontSee('<script>alert(1)</script>', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_security_headers_are_sent_on_web_responses(): void
    {
        $response = $this->get(route('login'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
    }
}
