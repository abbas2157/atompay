<?php

namespace Tests\Unit;

use App\Services\InstalmentQuoteService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Pins the quote math to AtomShop's checkout so a change on either side
 * shows up here before a customer sees two different numbers.
 */
class InstalmentQuoteServiceTest extends TestCase
{
    private InstalmentQuoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // Fix the config regardless of what the shared DB row currently says.
        Cache::put('atompay.calculator', ['tenures' => [3, 6, 9, 12], 'per_month' => 4.0], 60);
        $this->service = app(InstalmentQuoteService::class);
    }

    public function test_quote_matches_atomshop_checkout_formula(): void
    {
        $q = $this->service->quote(price: 100000, months: 6);

        $this->assertSame(20000, $q->advance);   // 20% minimum down
        $this->assertSame(80000, $q->financed);
        $this->assertSame(19200, $q->markup);    // 4% x 6 x 80,000
        $this->assertSame(119200, $q->total);
        $this->assertSame(16534, $q->monthly);   // ceil(99,200 / 6)
    }

    public function test_higher_advance_lowers_the_monthly(): void
    {
        $low  = $this->service->quote(100000, 6, 20000);
        $high = $this->service->quote(100000, 6, 60000);

        $this->assertLessThan($low->monthly, $high->monthly);
        $this->assertSame(9600, $high->markup);
    }

    public function test_rejects_tenure_not_offered(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->quote(100000, 7);
    }

    public function test_rejects_advance_outside_bounds(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->quote(100000, 6, 70000);
    }
}
