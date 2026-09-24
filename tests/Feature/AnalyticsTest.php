<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    public function test_the_tag_renders_with_the_csp_nonce_and_google_is_allowed(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $response = $this->get('/')->assertOk();
        $html = $response->getContent();
        $csp = $response->headers->get('Content-Security-Policy');

        preg_match("/'nonce-([^']+)'/", $csp, $m);
        $this->assertMatchesRegularExpression('/gtag\/js\?id=G-TEST123"\s+nonce="'.preg_quote($m[1], '/').'"/', $html);
        $this->assertMatchesRegularExpression('/<script\s+nonce="'.preg_quote($m[1], '/').'"\s*>\s*window\.dataLayer/', $html);
        $this->assertStringContainsString("gtag('config', \"G-TEST123\"", $html);
        $this->assertStringContainsString('"allow_google_signals":false', $html);
        $this->assertStringContainsString('"debug_mode":true', $html);       // not production

        $this->assertMatchesRegularExpression('/script-src [^;]*https:\/\/\*\.googletagmanager\.com/', $csp);
        $this->assertMatchesRegularExpression('/connect-src [^;]*https:\/\/\*\.google-analytics\.com/', $csp);
        $this->assertMatchesRegularExpression('/img-src [^;]*https:\/\/\*\.google-analytics\.com/', $csp);
    }

    public function test_no_id_means_no_tag_and_no_google_in_the_policy(): void
    {
        config(['services.google_analytics.measurement_id' => null]);

        $response = $this->get('/')->assertOk();

        $this->assertStringNotContainsString('googletagmanager', $response->getContent());
        $this->assertStringNotContainsString('google', $response->headers->get('Content-Security-Policy'));
    }

    public function test_the_staff_area_is_never_tracked(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);
        $staff = User::whereIn('role', config('atompay.staff_roles'))->where('status', 'active')->firstOrFail();

        $this->actingAs($staff)->get(route('staff.assessments.index'))
            ->assertOk()
            ->assertDontSee('googletagmanager', false);
    }
}
