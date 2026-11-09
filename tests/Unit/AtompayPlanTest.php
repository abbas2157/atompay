<?php

namespace Tests\Unit;

use App\Services\InstalmentQuoteService;
use Tests\TestCase;

/**
 * Pins AtomPay's own plan: eligible listed products only, always three months,
 * no advance, 0% markup, with a 3% processing fee shown as a disclaimer.
 *
 * These numbers must match AtomShop's
 * Website\InstallmentPlanService::atompayPlan() exactly - the shop re-prices
 * every order server-side, so a disagreement here is a page that quotes a
 * figure the customer will not be charged.
 */
class AtompayPlanTest extends TestCase
{
    private function quotes(): InstalmentQuoteService
    {
        return app(InstalmentQuoteService::class);
    }

    public function test_a_round_price_splits_into_three_equal_months(): void
    {
        $plan = $this->quotes()->atompayPlan(45000);

        $this->assertSame(3, $plan['months']);
        $this->assertSame(0, $plan['advance']);
        $this->assertSame(0, $plan['markup']);
        $this->assertSame(45000, $plan['financed']);
        $this->assertSame(15000, $plan['monthly']);
        $this->assertSame(15000, $plan['last_monthly']);
        $this->assertSame(45000, $plan['total']);
        $this->assertSame(1350, $plan['processing_fee']);
    }

    /** The last month takes the rounding, so the months add up to the price. */
    public function test_the_last_month_absorbs_the_rounding(): void
    {
        foreach ([100000, 38000, 32000, 22000, 999] as $price) {
            $plan = $this->quotes()->atompayPlan($price);

            $sum = $plan['monthly'] * ($plan['months'] - 1) + $plan['last_monthly'];

            $this->assertSame($price, $sum, "months do not add up to {$price}");
            $this->assertSame($price, $plan['total'], "total is not the cash price for {$price}");
            $this->assertGreaterThanOrEqual($plan['monthly'], $plan['last_monthly']);
        }
    }

    public function test_a_hundred_thousand_rupee_product_matches_atomshop(): void
    {
        $plan = $this->quotes()->atompayPlan(100000);

        $this->assertSame(33333, $plan['monthly']);
        $this->assertSame(33334, $plan['last_monthly']);
        $this->assertSame(3000, $plan['processing_fee']);
    }

    public function test_the_schedule_is_dated_and_flags_the_rounding_month(): void
    {
        $plan = $this->quotes()->atompayPlan(100000);

        $this->assertCount(3, $plan['schedule']);
        $this->assertSame([1, 2, 3], array_column($plan['schedule'], 'number'));

        // Dates run forward from today, one month apart.
        $this->assertTrue($plan['schedule'][0]['due']->isFuture());
        $this->assertTrue($plan['schedule'][2]['due']->greaterThan($plan['schedule'][0]['due']));

        $this->assertFalse($plan['schedule'][0]['rounding']);
        $this->assertTrue($plan['schedule'][2]['rounding']);
        $this->assertSame(33334, $plan['schedule'][2]['amount']);

        $this->assertSame(100000, array_sum(array_column($plan['schedule'], 'amount')));
    }

    public function test_it_falls_back_to_the_configured_example_price(): void
    {
        $plan = $this->quotes()->atompayPlan();

        $this->assertSame((int) config('atompay.atompay_plan.example_price'), $plan['price']);
    }

    /** Guards against a negative price producing a negative schedule. */
    public function test_a_negative_price_is_clamped_to_zero(): void
    {
        $plan = $this->quotes()->atompayPlan(-5000);

        $this->assertSame(0, $plan['price']);
        $this->assertSame(0, $plan['monthly']);
        $this->assertSame(0, $plan['total']);
        $this->assertSame(0, $plan['processing_fee']);
    }
}
