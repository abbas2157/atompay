<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * The deploy checklist, as a command. Every check here exists because the
 * thing it checks has actually broken a live deploy:
 *
 *   - the design helpers were missing, because composer.json's autoload.files
 *     list is only compiled when the autoloader is dumped, and SecurityHeaders
 *     calls design_is_v2() on every request - so the whole site 500'd;
 *   - product pictures loaded from atompay.shop instead of atomshop.pk, first
 *     from a wrong ATOMPAY_ASSET_URL, then from one misspelled ATOMAPY_, and
 *     then from a config cache still holding the old value.
 *
 * Run it after every deploy. It reads the live configuration, so it catches a
 * stale config cache that a test suite never can.
 */
class DeployCheck extends Command
{
    protected $signature = 'atompay:deploy-check {--no-network : Skip the checks that fetch a URL}';

    protected $description = 'Verify this deployment is configured correctly (run after every deploy)';

    private bool $ok = true;

    /** Whether the asset url passed, so later checks do not contradict it. */
    private bool $assetUrlSound = true;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>AtomPay deploy check</>');
        $this->line('  environment: '.app()->environment().'   url: '.config('app.url'));
        $this->newLine();

        $this->checkDesignHelpers();
        $this->checkAssetUrl();
        $this->checkCaches();
        $this->checkMigrations();

        if (! $this->option('no-network')) {
            $this->checkAPictureLoads();
        }

        $this->newLine();
        $this->ok
            ? $this->info('  Everything checks out.')
            : $this->error('  Something above needs fixing before this deploy is good.');
        $this->newLine();

        return $this->ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * SecurityHeaders calls design_is_v2() while building the CSP for every
     * request, so if the helpers are missing nothing renders at all.
     */
    private function checkDesignHelpers(): void
    {
        $missing = array_values(array_filter(
            ['design', 'design_is_v2', 'design_view', 'atompay_view', 'design_asset'],
            fn (string $fn) => ! function_exists($fn),
        ));

        $missing === []
            ? $this->good('design helpers loaded', 'design: '.design())
            : $this->bad('design helpers MISSING: '.implode(', ', $missing), 'run: composer dump-autoload');
    }

    /** Pictures live on AtomShop; anything else 404s every image on the site. */
    private function checkAssetUrl(): void
    {
        $url = (string) config('atompay.asset_url');
        $shopHost = parse_url((string) config('atompay.shop_url'), PHP_URL_HOST);
        $assetHost = parse_url($url, PHP_URL_HOST);

        if (! preg_match('#^https?://#', $url)) {
            $this->assetUrlSound = false;
            $this->bad("asset url is not absolute: {$url}", 'a relative value resolves against AtomPay and 404s');

            return;
        }

        if (stripos($url, 'atompay') !== false) {
            $this->assetUrlSound = false;
            $this->bad("asset url points at AtomPay: {$url}", 'pictures are served by AtomShop, not by this app');

            return;
        }

        if (! str_contains($url, '/public')) {
            $this->assetUrlSound = false;
            $this->bad("asset url is missing /public: {$url}", "the shop serves products.picture under /public");

            return;
        }

        $assetHost === $shopHost
            ? $this->good('asset url', $url)
            : $this->warn("  ~ asset url host ({$assetHost}) differs from the shop ({$shopHost}) - expected in development");
    }

    /**
     * A cached config survives .env edits, which is how a corrected value can
     * keep serving the old one.
     */
    private function checkCaches(): void
    {
        if (! file_exists($this->laravel->getCachedConfigPath())) {
            $this->good('config not cached', 'reads .env directly');

            return;
        }

        // A cached config survives .env edits, so when something above is
        // already wrong this is the first thing to suspect: the value being
        // reported may not be the one in .env at all.
        $this->ok
            ? $this->warn('  ~ config is cached - run `php artisan config:clear` after any .env change')
            : $this->bad(
                'config is cached, so the values above may not be the ones in .env',
                'clear it first, then re-run: php artisan config:clear && php artisan atompay:deploy-check',
            );
    }

    private function checkMigrations(): void
    {
        try {
            $migrator = $this->laravel->make('migrator');
            $files = $migrator->getMigrationFiles($this->laravel->databasePath('migrations'));
            $ran = $migrator->getRepository()->getRan();
            $waiting = array_diff(array_keys($files), $ran);

            $waiting === []
                ? $this->good('migrations', count($files).' applied')
                : $this->bad(count($waiting).' migration(s) pending: '.implode(', ', $waiting), 'run: php artisan migrate --force');
        } catch (\Throwable $e) {
            $this->bad('could not read migrations: '.$e->getMessage());
        }
    }

    /**
     * The end-to-end proof: take a real product and fetch its picture. This is
     * the check that catches a typo, a wrong host, a missing /public and a
     * stale config cache all at once.
     */
    private function checkAPictureLoads(): void
    {
        $product = Product::query()->visible()->atompayEligible()
            ->whereNotNull('picture')->first(['id', 'title', 'picture']);

        if (! $product) {
            $this->warn('  ~ no eligible product with a picture to test');

            return;
        }

        try {
            $status = Http::timeout(15)->head($product->picture_url)->status();
        } catch (\Throwable $e) {
            $this->bad('could not fetch a product picture: '.$e->getMessage(), $product->picture_url);

            return;
        }

        if ($status === 200) {
            $this->good('product pictures load', $product->picture_url);

            return;
        }

        if (! $this->assetUrlSound) {
            $this->bad(
                "a product picture returned HTTP {$status}, from the bad url reported above",
                $product->picture_url,
            );

            return;
        }

        // The url's shape is sound, so a 404 means the file is not on the shop -
        // a content gap (common in development), not a bad deploy.
        $this->warn("  ~ a product picture returned HTTP {$status} - the url is well formed, so the file is");
        $this->warn('    missing from the shop rather than misconfigured here: '.$product->picture_url);
    }

    private function good(string $what, string $detail = ''): void
    {
        $this->line("  <fg=green>✓</> {$what}".($detail ? "  <fg=gray>{$detail}</>" : ''));
    }

    private function bad(string $what, string $hint = ''): void
    {
        $this->ok = false;
        $this->line("  <fg=red>✗</> {$what}".($hint ? "\n      <fg=yellow>{$hint}</>" : ''));
    }
}
