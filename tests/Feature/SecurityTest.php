<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('global');
    }

    public function test_every_page_carries_the_hardening_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');

        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
    }

    public function test_the_csp_blocks_framing_and_stray_object_sources(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertMatchesRegularExpression("/script-src [^;]*'nonce-/", $csp);
    }

    public function test_structured_data_carries_the_nonce_so_the_csp_does_not_drop_it(): void
    {
        $html = $this->get('/')->getContent();
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        // A JSON-LD block with no nonce would be silently discarded by the browser.
        if (str_contains($html, 'application/ld+json')) {
            $this->assertMatchesRegularExpression('/application\/ld\+json"\s+nonce="[^"]+"/', $html);
        }

        $this->assertNotEmpty($csp);
    }

    public function test_hsts_is_not_sent_outside_production(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        RateLimiter::clear('login');

        $attempt = fn () => $this->post('/login', [
            'login' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        // config('security.rate_limits.login') is 5 per minute per identifier+IP.
        for ($i = 0; $i < 5; $i++) {
            $attempt()->assertRedirect();
        }

        $attempt()->assertStatus(429);
    }

    public function test_a_customer_cannot_read_another_customers_kyc_document(): void
    {
        $owner = User::customers()->active()->has('kycProfile')->firstOrFail();
        $stranger = User::customers()->active()->where('id', '!=', $owner->id)->firstOrFail();
        $profile = $owner->kycProfile;

        // The owner may read it; nobody else may, whatever id they guess.
        $this->actingAs($owner)->get("/documents/{$profile->id}/cnic-front")->assertSuccessful();
        $this->actingAs($stranger)->get("/documents/{$profile->id}/cnic-front")->assertForbidden();
    }
}
