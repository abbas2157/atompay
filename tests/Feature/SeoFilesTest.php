<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoFilesTest extends TestCase
{
    public function test_sitemap_lists_only_public_pages_with_lastmod(): void
    {
        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'sitemap is well-formed XML');

        $locs = array_map('strval', $doc->xpath('//*[local-name()="loc"]'));
        $this->assertSame([route('home'), route('faq')], $locs);

        foreach ($doc->xpath('//*[local-name()="lastmod"]') as $lastmod) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $lastmod);
        }
    }

    public function test_production_robots_blocks_private_areas_and_points_at_the_sitemap(): void
    {
        $this->app['env'] = 'production';

        $body = $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        foreach (['/my', '/staff', '/documents', '/login', '/register', '/api/'] as $path) {
            $this->assertStringContainsString("Disallow: {$path}\n", $body);
        }
        $this->assertStringContainsString('Allow: /', $body);
        // Must be absolute - a relative Sitemap line is ignored by crawlers.
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $body);
        $this->assertMatchesRegularExpression('/^Sitemap: https?:\/\//m', $body);
    }

    public function test_non_production_robots_blocks_everything(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertSame("User-agent: *\nDisallow: /\n", $body);
    }
}
