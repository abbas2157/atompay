<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * What the Google Play / App Store submission needs from the server: the
 * reviewer accounts, and the fields the app shows when they are present
 * (limit.available_after_next, lowest_eligible_price, order.delivered_at,
 * paid_via).
 */
class StoreReleaseApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('atompay.kyc.disk'));
        Mail::fake();
    }

    /* ------------------------------------------------------- review accounts */

    private function configureReviewAccounts(): void
    {
        config(['atompay.review_accounts' => [
            'a' => ['email' => 'review-a-test@example.test', 'password' => 'Review-A-pass-1'],
            'b' => ['email' => 'review-b-test@example.test', 'password' => 'Review-B-pass-1'],
        ]]);
    }

    private function signIn(string $email, string $password): string
    {
        return $this->freshRequest()->postJson('/api/v1/auth/login', ['login' => $email, 'password' => $password, 'device_name' => 'Review'])
            ->assertOk()->json('data.token');
    }

    public function test_review_a_has_content_on_every_screen(): void
    {
        $this->configureReviewAccounts();
        $this->artisan('atompay:seed-review-accounts')->assertSuccessful();

        $token = $this->signIn('review-a-test@example.test', 'Review-A-pass-1');

        $dashboard = $this->freshRequest()->withToken($token)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.kyc_status', 'verified')
            ->assertJsonPath('data.limit.has_limit', true)
            ->assertJsonPath('data.next_due.state', 'due')
            ->json('data');
        $this->assertGreaterThan(0, $dashboard['unread_notifications']);
        $this->assertGreaterThan($dashboard['limit']['available'], $dashboard['limit']['available_after_next']);

        $plans = $this->freshRequest()->withToken($token)->getJson('/api/v1/plans')->assertOk()->assertJsonCount(1, 'data')->json('data');
        $this->assertNotNull($plans[0]['order']['delivered_at']);

        $states = collect($this->freshRequest()->withToken($token)->getJson('/api/v1/plans/'.$plans[0]['order']['id'])->assertOk()->json('data.instalments'));
        $this->assertSame(['paid', 'due', 'upcoming'], $states->pluck('state')->all());
        $this->assertSame('JazzCash', $states[0]['paid_via']);
        $this->assertNull($states[1]['paid_via']);

        $this->freshRequest()->withToken($token)->getJson('/api/v1/profile')->assertOk();
        Storage::disk(config('atompay.kyc.disk'))->assertExists(User::where('email', 'review-a-test@example.test')->first()->kycProfile->selfie_path);
    }

    public function test_review_b_can_be_deleted_and_the_command_brings_both_back(): void
    {
        $this->configureReviewAccounts();
        $this->artisan('atompay:seed-review-accounts')->assertSuccessful();
        $a = User::where('email', 'review-a-test@example.test')->first();

        $tokenB = $this->signIn('review-b-test@example.test', 'Review-B-pass-1');
        $this->freshRequest()->withToken($tokenB)->getJson('/api/v1/plans')->assertOk()->assertJsonCount(0, 'data');
        $this->freshRequest()->withToken($tokenB)->postJson('/api/v1/me/delete', ['password' => 'Review-B-pass-1'])->assertNoContent();

        // A reviewer changed A's password and its schedule; a second run puts everything back.
        $a->forceFill(['password' => 'changed-by-a-reviewer'])->save();
        DB::table('order_instalments')->where('user_id', $a->id)->update(['status' => 'Paid']);

        $this->artisan('atompay:seed-review-accounts')->assertSuccessful();

        $this->assertSame($a->id, User::where('email', 'review-a-test@example.test')->value('id'), 'A is reset in place, not duplicated');
        $this->assertSame(1, DB::table('orders')->where('user_id', $a->id)->count());
        $this->signIn('review-a-test@example.test', 'Review-A-pass-1');
        $this->signIn('review-b-test@example.test', 'Review-B-pass-1');   // a fresh B: the deleted one was anonymised
    }

    public function test_it_never_takes_over_someone_elses_account(): void
    {
        $this->configureReviewAccounts();
        $real = $this->makeCustomer(['email' => 'review-a-test@example.test', 'name' => 'A Real Customer']);
        $this->makeOrder($real, [[now()->addMonth(), 5000, false]]);

        $this->artisan('atompay:seed-review-accounts')->assertFailed();

        $this->assertSame('A Real Customer', $real->fresh()->name);
        $this->assertSame(1, DB::table('orders')->where('user_id', $real->id)->count());
    }

    public function test_it_refuses_without_credentials_in_env(): void
    {
        config(['atompay.review_accounts' => ['a' => ['email' => null, 'password' => null], 'b' => ['email' => null, 'password' => null]]]);

        $this->artisan('atompay:seed-review-accounts')->assertFailed();
    }

    /* ------------------------------------------------------ privacy policy */

    public function test_the_privacy_policy_states_the_business_facts_once_configured(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('not use it for advertising or tracking')
            ->assertSee('Account → Settings → Delete account')
            ->assertSee(route('account.delete.info'))
            ->assertSee('Ask Atom')
            ->assertDontSee('stored on our servers in');

        config([
            'atompay.lender' => ['name' => 'Example Finance (Pvt) Ltd', 'licence' => 'SECP/123', 'address' => null],
            'atompay.privacy' => ['data_location' => 'Pakistan (Karachi)', 'record_retention_years' => 10, 'credit_bureau' => null],
        ]);

        $this->get('/privacy')->assertOk()
            ->assertSee('Example Finance (Pvt) Ltd')
            ->assertSee('licence SECP/123')
            ->assertSee('stored on our servers in Pakistan (Karachi)')
            ->assertSee('10 years after the plan is repaid');
        $this->get('/privacy-policy')->assertRedirect('/privacy');
    }

    /* ---------------------------------------------------------- new fields */

    public function test_available_after_next_is_what_available_becomes_once_next_due_is_paid(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);
        $this->makeDecidedAssessment($user, limit: 60000);
        $this->makeAtomPayOrder($user, [[now()->addDays(3), 15666, false], [now()->addDays(33), 15666, false]]);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.limit.available', 60000 - 31332)
            ->assertJsonPath('data.limit.available_after_next', 60000 - 15666);
    }

    public function test_available_after_next_frees_the_whole_instalment_even_when_part_paid(): void
    {
        $user = $this->makeCustomer();
        $this->makeDecidedAssessment($user, limit: 30000);
        $this->makeAtomPayOrder($user, [[now()->addDays(3), 20000, false], [now()->addDays(33), 20000, false]]);
        DB::table('order_instalments')->where('user_id', $user->id)->orderBy('installment_date')->limit(1)->update(['installment_paid_price' => 5000]);

        // Used 40,000 of 30,000: nothing available now. Paying the rest of the first frees all 20,000 of it.
        $this->withToken($this->tokenFor($user))->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.limit.available', 0)
            ->assertJsonPath('data.limit.available_after_next', 10000);
    }

    public function test_the_new_dashboard_fields_are_null_without_a_limit_or_anything_due(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.limit.available_after_next', null)
            ->assertJsonPath('data.lowest_eligible_price', null);

        $this->makeDecidedAssessment($user, limit: 50000);
        $this->freshRequest()->withToken($token)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.limit.available_after_next', null);    // a limit, but nothing due
    }

    public function test_lowest_eligible_price_matches_the_cheapest_product_listed(): void
    {
        $user = $this->makeCustomer();
        $this->makeDecidedAssessment($user);
        $token = $this->tokenFor($user);

        $lowest = $this->withToken($token)->getJson('/api/v1/dashboard')->assertOk()->json('data.lowest_eligible_price');
        $cheapest = app(\App\Services\CatalogueService::class)->withinLimit(PHP_INT_MAX, 1000)->min('price');

        $this->assertSame($cheapest === null ? null : (int) $cheapest, $lowest);
    }

    public function test_delivered_at_comes_from_the_status_history_and_stays_set(): void
    {
        $user = $this->makeCustomer();
        $normal = $this->makeOrder($user, [[now()->addMonth(), 5000, false]]);
        $atompay = $this->makeAtomPayOrder($user, [[now()->addMonth(), 5000, false]]);
        $processing = $this->makeOrder($user, [], 'Processing');
        $cancelled = $this->makeOrder($user, [], 'Cancelled');
        $mirror = DB::table('custom_orders')->where('source_order_id', $atompay)->value('id');

        // AtomShop writes Karachi wall-clock time.
        $history = fn (int $id, string $type, string $status, string $at) => DB::table('order_change_histories')->insert([
            'order_id' => $id, 'order_type' => $type, 'status' => $status, 'role' => 'admin', 'created_at' => $at, 'updated_at' => $at,
        ]);
        $history($normal, 'Normal', 'Delivered', '2026-09-21 15:40:00');
        $history($normal, 'Normal', 'Instalments', '2026-09-22 09:00:00');
        $history($mirror, 'Custom', 'Delivered', '2026-11-09 21:30:00');   // after 7 pm: still 9 Nov in Pakistan
        $history($cancelled, 'Normal', 'Delivered', '2026-10-01 10:00:00');
        $token = $this->tokenFor($user);

        $delivered = collect($this->withToken($token)->getJson('/api/v1/plans')->assertOk()->json('data'))
            ->mapWithKeys(fn ($p) => [$p['order']['id'] => $p['order']['delivered_at']]);

        $this->assertSame('2026-09-21T15:40:00+05:00', $delivered[$normal]);
        $this->assertSame('2026-11-09T21:30:00+05:00', $delivered[$atompay]);
        $this->assertNull($delivered[$processing]);
        $this->assertNull($delivered[$cancelled]);

        $this->freshRequest()->withToken($token)->getJson("/api/v1/plans/{$atompay}")->assertOk()
            ->assertJsonPath('data.order.delivered_at', '2026-11-09T21:30:00+05:00');
    }

    public function test_paid_via_is_a_display_label_and_null_until_paid(): void
    {
        $user = $this->makeCustomer();
        $orderId = $this->makeOrder($user, [
            [now()->subMonths(4), 1000, true], [now()->subMonths(3), 1000, true], [now()->subMonths(2), 1000, true],
            [now()->subMonth(), 1000, true], [now()->addDays(3), 1000, false],
        ]);
        $methods = ['easypaisa', 'Bank', 'By Hand', 'Online', 'JazzCash'];   // as AtomShop's screens store them
        foreach (DB::table('order_instalments')->where('user_id', $user->id)->orderBy('installment_date')->pluck('id') as $n => $id) {
            DB::table('order_instalments')->where('id', $id)->update(['payment_method' => $methods[$n]]);
        }
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson("/api/v1/plans/{$orderId}")->assertOk()
            ->assertJsonPath('data.instalments.*.paid_via', ['EasyPaisa', 'Bank transfer', 'Cash', null, null]);
        $this->freshRequest()->withToken($token)->getJson('/api/v1/dashboard')->assertJsonPath('data.next_due.paid_via', null);
    }
}
