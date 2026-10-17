<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The shared page furniture: share previews, WhatsApp, cookie banner, search console. */
class LaunchChromeTest extends TestCase
{
    public function test_the_default_share_image_exists_and_is_declared_with_its_size(): void
    {
        $this->assertFileExists(public_path('images/og-default.jpg'));

        $this->get('/')
            ->assertOk()
            ->assertSee('images/og-default.jpg', false)
            ->assertSee('<meta property="og:image:width" content="1200">', false);
    }

    public function test_the_whatsapp_button_uses_the_international_number(): void
    {
        config(['atompay.api.support.whatsapp' => '0300 1234567']);

        $this->get('/')->assertOk()->assertSee('https://wa.me/923001234567?text=', false);
    }

    public function test_no_whatsapp_number_means_no_button(): void
    {
        config(['atompay.api.support.whatsapp' => null]);

        $this->get('/')->assertOk()->assertDontSee('wa.me/', false);
    }

    public function test_the_cookie_banner_and_consent_default_come_with_analytics_only(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);
        $this->get('/')->assertOk()
            ->assertSee('x-data="cookieBanner"', false)
            ->assertSee("gtag('consent', 'default'", false);

        config(['services.google_analytics.measurement_id' => null]);
        $this->get('/')->assertOk()->assertDontSee('cookieBanner', false);
    }

    public function test_search_console_verification_tag(): void
    {
        config(['services.google_search_console.verification' => 'abc123']);
        $this->get('/')->assertSee('<meta name="google-site-verification" content="abc123">', false);

        config(['services.google_search_console.verification' => null]);
        $this->get('/')->assertDontSee('google-site-verification', false);
    }

    public function test_outbound_shop_links_carry_utm_tags_but_structured_data_does_not(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('utm_source=atompay&amp;utm_medium=referral', $html);
        $this->assertDoesNotMatchRegularExpression('/"url":"[^"]*utm_/', $html);
    }

    public function test_the_faq_page_has_a_search_box(): void
    {
        $this->get('/faq')->assertOk()->assertSee('id="faq-search"', false)->assertSee('data-search=', false);
    }
}
