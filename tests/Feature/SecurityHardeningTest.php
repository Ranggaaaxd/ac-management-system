<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_responses_include_security_headers(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-XSS-Protection', '1; mode=block')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=()');
    }

    public function test_session_cookie_security_settings_match_the_required_policy(): void
    {
        $this->assertTrue(config('session.http_only'));
        $this->assertSame('lax', config('session.same_site'));
        $this->assertFalse(config('session.encrypt'));
    }

    public function test_filament_login_is_limited_to_five_attempts_per_ip_per_minute(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        auth()->logout();

        $key = 'livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1');
        RateLimiter::clear($key);

        $login = Livewire::test(Login::class)
            ->set('data.email', 'unknown@example.test')
            ->set('data.password', 'invalid-password');

        foreach (range(1, 5) as $attempt) {
            $login->call('authenticate');
        }

        $this->assertSame(5, RateLimiter::attempts($key));

        $login->call('authenticate');

        $this->assertSame(5, RateLimiter::attempts($key));
    }
}
