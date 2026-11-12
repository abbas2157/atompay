<?php

namespace Tests\Feature\Api;

use App\Services\InstalmentQuoteService;

class PlanApiTest extends ApiTestCase
{
    public function test_plans_list_orders_with_progress(): void
    {
        $user = $this->makeCustomer();
        $orderId = $this->makeOrder($user, [
            [now()->subMonths(2), 10000, true],
            [now()->subDays(5), 10000, false],       // late
            [now()->addMonth(), 10000, false],
        ]);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order.id', $orderId)
            ->assertJsonPath('data.0.order.reference', 'AS-'.str_pad((string) $orderId, 5, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.0.order.financed', 116000)
            ->assertJsonPath('data.0.state', 'late')
            ->assertJsonPath('data.0.progress.paid_count', 1)
            ->assertJsonPath('data.0.progress.total_count', 3)
            ->assertJsonPath('data.0.progress.remaining_amount', 20000)
            ->assertJsonPath('data.0.next_due.state', 'late')
            ->assertJsonMissingPath('data.0.instalments');
    }

    public function test_orders_in_every_status_are_listed_with_their_state(): void
    {
        $user = $this->makeCustomer();
        $completed = $this->makeOrder($user, [[now()->subMonth(), 5000, true]], 'Completed');
        $cancelled = $this->makeOrder($user, [[now()->subMonth(), 5000, false]], 'Cancelled');
        $pending = $this->makeOrder($user, [], 'Pending');
        $verifying = $this->makeOrder($user, [], 'Varification');
        $processing = $this->makeOrder($user, [], 'Processing');
        $token = $this->tokenFor($user);

        $states = collect($this->withToken($token)->getJson('/api/v1/plans')->assertOk()->json('data'))
            ->mapWithKeys(fn ($plan) => [$plan['order']['id'] => [$plan['state'], $plan['order']['status_label']]]);

        $this->assertEquals([
            $completed => ['completed', 'Completed'],
            $cancelled => ['cancelled', 'Cancelled'],                // an unpaid row on a cancelled order is not "late"
            $pending => ['pending', 'Pending'],
            $verifying => ['pending', 'Verification'],
            $processing => ['processing', 'Processing'],
        ], $states->all());

        $this->freshRequest()->withToken($token)->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.plans.active_count', 1)          // only Processing is still being repaid
            ->assertJsonPath('data.plans.pending_count', 2)
            ->assertJsonPath('data.plans.has_late', false);
    }

    /**
     * A pending order is listed with what happens next, in whichever design is
     * live. v1 lists orders on the dashboard; v2 splits them onto /my/orders
     * and keeps the dashboard as an overview, so the page differs but the
     * behaviour must not.
     */
    public function test_the_web_dashboard_lists_a_pending_order_with_what_happens_next(): void
    {
        $user = $this->makeCustomer();
        $orderId = $this->makeOrder($user, [], 'Pending');
        $reference = 'AS-'.str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);

        foreach ([
            'v1' => [route('account.dashboard'), 'Atomshop is reviewing this order.'],
            'v2' => [route('account.orders'), 'Atomshop is reviewing this order.'],
        ] as $design => [$url, $note]) {
            config(['design.default' => $design, 'design.active' => $design]);

            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee($reference)
                ->assertSee('Awaiting approval')
                ->assertSee($note);
        }
    }

    public function test_a_plan_shows_its_schedule_only_to_its_owner(): void
    {
        $owner = $this->makeCustomer();
        $orderId = $this->makeOrder($owner, [
            [now()->subMonth(), 10000, true],
            [now()->addDays(5), 10000, false],
        ]);

        $this->withToken($this->tokenFor($owner))->getJson("/api/v1/plans/{$orderId}")
            ->assertOk()
            ->assertJsonCount(2, 'data.instalments')
            ->assertJsonPath('data.instalments.0.state', 'paid')
            ->assertJsonPath('data.instalments.0.paid_amount', 10000)
            ->assertJsonPath('data.instalments.1.state', 'due')
            ->assertJsonPath('data.instalments.1.due_date', now()->addDays(5)->toDateString());

        $stranger = $this->makeCustomer();
        $this->freshRequest()->withToken($this->tokenFor($stranger))->getJson("/api/v1/plans/{$orderId}")->assertNotFound();
    }

    public function test_the_dashboard_names_the_next_payment(): void
    {
        $user = $this->makeCustomer();
        $orderId = $this->makeOrder($user, [[now()->addDays(3), 12500, false]]);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.next_due.amount', 12500)
            ->assertJsonPath('data.next_due.order_id', $orderId)
            ->assertJsonPath('data.next_due.order_reference', 'AS-'.str_pad((string) $orderId, 5, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.plans.active_count', 1)
            ->assertJsonPath('data.plans.has_late', false);
    }

    public function test_an_atompay_order_shows_the_schedule_atomshop_keeps_on_its_mirror(): void
    {
        $user = $this->makeCustomer();
        $orderId = $this->makeAtomPayOrder($user, [
            [now()->subMonth(), 2666, true],
            [now()->addDays(4), 2666, false],
            [now()->addMonths(2), 2668, false],
        ]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/plans')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.order.id', $orderId)
            ->assertJsonPath('data.0.progress.paid_count', 1)
            ->assertJsonPath('data.0.progress.total_count', 3)
            ->assertJsonPath('data.0.progress.remaining_amount', 5334)
            ->assertJsonPath('data.0.next_due.order_id', $orderId);

        $this->freshRequest()->withToken($token)->getJson("/api/v1/plans/{$orderId}")
            ->assertOk()
            ->assertJsonCount(3, 'data.instalments')
            ->assertJsonPath('data.instalments.1.state', 'due')
            ->assertJsonPath('data.instalments.2.order_id', $orderId);

        $this->freshRequest()->withToken($token)->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.next_due.amount', 2666)
            ->assertJsonPath('data.next_due.order_id', $orderId)
            ->assertJsonPath('data.next_due.order_reference', 'AS-'.str_pad((string) $orderId, 5, '0', STR_PAD_LEFT));
    }

    public function test_atompay_orders_use_the_limit_like_atomshop_admin_counts_them(): void
    {
        $user = $this->makeCustomer();
        $this->makeDecidedAssessment($user, 'approved', 30000);
        $this->makeOrder($user, [[now()->addMonth(), 1000, false]]);                       // Normal order
        $this->makeAtomPayOrder($user, [[now()->subMonth(), 2666, true], [now()->addMonth(), 2666, false]]);
        $this->makeAtomPayOrder($user, [], 'Delivered');                                     // approved, not yet scheduled: 10000 - 2000

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.limit.approved', 30000)
            ->assertJsonPath('data.limit.used', 1000 + 2666 + 8000)
            ->assertJsonPath('data.limit.available', 30000 - 11666);
    }

    /** AtomPay's plan, not seller financing: 3 months, no advance, 0% markup. */
    public function test_calculator_config_and_quote_price_atompays_plan(): void
    {
        $this->getJson('/api/v1/calculator')
            ->assertOk()
            ->assertJsonPath('data.plan', 'atompay')
            ->assertJsonPath('data.tenures', [3])
            ->assertJsonPath('data.per_month_percentage', 0)
            ->assertJsonPath('data.advance', ['min_ratio' => 0, 'max_ratio' => 0]);

        $this->postJson('/api/v1/quote', ['price' => 100000, 'months' => 3])
            ->assertOk()
            ->assertJsonPath('data.plan', 'atompay')
            ->assertJsonPath('data.advance', 0)
            ->assertJsonPath('data.markup', 0)
            ->assertJsonPath('data.total', 100000)
            ->assertJsonPath('data.monthly', 33333)
            ->assertJsonPath('data.last_monthly', 33334)
            ->assertJsonPath('data.processing_fee', 3000)
            ->assertJsonPath('data.advance_bounds', ['min' => 0, 'max' => 0]);

        // An explicit zero advance is fine.
        $this->postJson('/api/v1/quote', ['price' => 100000, 'months' => 3, 'advance' => 0])->assertOk();
    }

    public function test_a_bad_tenure_or_down_payment_is_a_field_error(): void
    {
        $this->postJson('/api/v1/quote', ['price' => 100000, 'months' => 6, 'advance' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['months', 'advance'])
            ->assertJsonPath('errors.months.0', 'AtomPay plans are always 3 months.')
            ->assertJsonPath('errors.advance.0', 'AtomPay takes no down payment.');
    }
}
