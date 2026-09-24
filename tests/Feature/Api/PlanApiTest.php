<?php

namespace Tests\Feature\Api;

use App\Services\InstalmentQuoteService;

class PlanApiTest extends ApiTestCase
{
    public function test_plans_list_active_orders_with_progress(): void
    {
        $user = $this->makeCustomer();
        $orderId = $this->makeOrder($user, [
            [now()->subMonths(2), 10000, true],
            [now()->subDays(5), 10000, false],       // late
            [now()->addMonth(), 10000, false],
        ]);
        $this->makeOrder($user, [[now()->subMonth(), 5000, true]], 'Completed');

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

    public function test_completed_plans_are_included_on_request(): void
    {
        $user = $this->makeCustomer();
        $this->makeOrder($user, [[now()->subMonth(), 5000, true]], 'Completed');

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/plans?include=completed')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.state', 'completed')
            ->assertJsonPath('data.0.next_due', null);
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

    public function test_calculator_config_and_quote_use_the_checkout_math(): void
    {
        $quotes = app(InstalmentQuoteService::class);
        $months = $quotes->tenures()[0];
        $expected = $quotes->quote(100000, $months)->toArray();

        $this->getJson('/api/v1/calculator')
            ->assertOk()
            ->assertJsonPath('data.tenures', $quotes->tenures())
            ->assertJsonPath('data.advance.min_ratio', 0.2);

        $this->postJson('/api/v1/quote', ['price' => 100000, 'months' => $months])
            ->assertOk()
            ->assertJsonPath('data.monthly', $expected['monthly'])
            ->assertJsonPath('data.total', $expected['total'])
            ->assertJsonPath('data.advance', 20000)
            ->assertJsonPath('data.advance_bounds', ['min' => 20000, 'max' => 60000]);
    }

    public function test_a_bad_tenure_or_down_payment_is_a_field_error(): void
    {
        // Tenures come from AtomShop's calculator row, so pick one it doesn't offer.
        $offered = app(InstalmentQuoteService::class)->tenures();
        $unoffered = collect(range(1, 60))->first(fn ($m) => ! in_array($m, $offered, true));

        $this->postJson('/api/v1/quote', ['price' => 100000, 'months' => $unoffered, 'advance' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['months', 'advance'])
            ->assertJsonPath('errors.advance.0', 'Down payment must be between PKR 20,000 and PKR 60,000.');
    }
}
