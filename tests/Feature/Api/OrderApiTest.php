<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Enums\VerificationStatus;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * "Buy with AtomPay" (docs/api/orders.md): GET /products/{product}/kfs and
 * POST /orders, which checks the limit and cap and has Atomshop place the
 * order (faked here; Atomshop's side is tested in atomshop's
 * AtompayServerOrderTest).
 */
class OrderApiTest extends ApiTestCase
{
    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'atompay.orders.enabled' => true,
            'atompay.orders.server_key' => 'test-key',
            'atompay.orders.atomshop_api' => 'https://shop.test/api',
        ]);

        $this->product = Product::query()->visible()->atompayEligible()
            ->where('price', '>', 0)->where('price', '<=', 60000)->orderBy('price')->first()
            ?? $this->markTestSkipped('No AtomPay-eligible product at or under Rs. 60,000.');

        $area = Area::query()->whereNotNull('city_id')->first() ?? $this->markTestSkipped('Needs an area with a city.');
        $this->user = $this->makeCustomer();
        $this->makeProfile($this->user, VerificationStatus::Verified)
            ->forceFill(['city_id' => $area->city_id, 'area_id' => $area->id])->save();
        $this->makeDecidedAssessment($this->user, 'approved', 100000);
    }

    public function test_the_statement_for_a_product_has_no_order_and_no_dates(): void
    {
        $data = $this->kfs()->assertOk()->json('data');

        $price = (int) $this->product->price;
        $this->assertNull($data['order_id']);
        $this->assertSame($price, $data['amount_financed']);
        $this->assertSame($price, $data['total_repayable']);
        $this->assertSame(3, $data['months']);
        $this->assertFalse($data['schedule_final']);
        $this->assertSame([null, null, null], array_column($data['instalments'], 'due_date'));
        $this->assertNull($data['lender']['licence']);
    }

    public function test_an_order_is_placed_through_atomshop_with_the_verified_address(): void
    {
        $price = (int) $this->product->price;
        Http::fake(['shop.test/*' => Http::response(['success' => true, 'data' => [
            'order_id' => 321, 'product' => $this->product->title, 'total' => $price, 'advance' => 0,
            'months' => 3, 'monthly' => intdiv($price, 3), 'last_monthly' => $price - 2 * intdiv($price, 3), 'area' => 'Johar Town',
        ]])]);

        $this->order()->assertCreated()
            ->assertJsonPath('data.order_id', 321)
            ->assertJsonPath('data.order_number', 'AS-00321')
            ->assertJsonPath('data.instalments.2', $price - 2 * intdiv($price, 3));

        $profile = $this->user->kycProfile()->first();
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://shop.test/api/atompay/orders'
            && $r->header('X-AtomPay-Key')[0] === 'test-key'
            && $r['user_id'] === $this->user->id
            && $r['kfs_amount'] === $price
            && $r['area_id'] === $profile->area_id
            && $r['address'] === $profile->residential_address);
    }

    public function test_atomshops_refusal_reaches_the_customer_as_a_field_error(): void
    {
        Http::fake(['shop.test/*' => Http::response(['success' => false, 'message' => 'The price of this product has changed to Rs. 50,000.'], 422)]);

        $this->order()->assertStatus(422)->assertJsonPath('errors.order.0', 'The price of this product has changed to Rs. 50,000.');
    }

    public function test_without_ticking_the_statement_nothing_is_sent(): void
    {
        Http::fake();

        $this->order(['accept_kfs' => false])->assertStatus(422)->assertJsonValidationErrors('accept_kfs');
        Http::assertNothingSent();
    }

    public function test_a_price_over_what_is_available_is_refused_before_atomshop(): void
    {
        Http::fake();
        $this->user->activeAssessment()->first()->forceFill(['approved_limit' => (int) $this->product->price - 1])->save();

        $this->order()->assertStatus(409)->assertJsonPath('code', 'over_limit');
        Http::assertNothingSent();
    }

    public function test_orders_awaiting_approval_count_against_the_limit(): void
    {
        Http::fake();
        // Rs. 1 short of room for this product once the pending order is counted.
        $pending = 100000 - (int) $this->product->price + 1;
        $orderId = $this->makeAtomPayOrder($this->user, [], 'Pending');
        DB::table('orders')->where('id', $orderId)->update(['total_deal_price' => $pending, 'advance_price' => 0]);

        $this->order()->assertStatus(409)->assertJsonPath('code', 'over_limit')
            ->assertJsonPath('message', 'You already have Rs. '.number_format($pending).' in orders awaiting approval, so Rs. '
                .number_format(100000 - $pending).' of your limit is left for new orders.');
        Http::assertNothingSent();

        // Cancelled orders don't count.
        DB::table('orders')->where('id', $orderId)->update(['status' => 'Cancelled']);
        $this->kfs()->assertOk();
    }

    public function test_a_month_over_the_cap_is_refused(): void
    {
        Http::fake();
        $this->user->activeAssessment()->first()->forceFill(['max_instalment' => 1000])->save();

        $this->kfs()->assertStatus(409)->assertJsonPath('code', 'over_cap');
        $this->order()->assertStatus(409)->assertJsonPath('code', 'over_cap');
        Http::assertNothingSent();
    }

    public function test_switched_off_it_says_so_and_app_config_tells_the_app(): void
    {
        config(['atompay.orders.enabled' => false]);

        $this->order()->assertStatus(409)->assertJsonPath('code', 'orders_unavailable');
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.features.in_app_orders', false);

        config(['atompay.orders.enabled' => true]);
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.features.in_app_orders', true);
    }

    public function test_a_customer_without_a_limit_is_sent_to_apply(): void
    {
        $other = $this->makeCustomer();

        $this->withToken($this->tokenFor($other))->getJson('/api/v1/products/'.$this->product->id.'/kfs')
            ->assertStatus(409)->assertJsonPath('code', 'limit_required');
    }

    private function kfs()
    {
        return $this->withToken($this->tokenFor($this->user))->getJson('/api/v1/products/'.$this->product->id.'/kfs');
    }

    private function order(array $fields = [])
    {
        return $this->withToken($this->tokenFor($this->user))->postJson('/api/v1/orders', $fields + [
            'product_id' => $this->product->id,
            'kfs_amount' => (int) $this->product->price,
            'accept_kfs' => true,
        ]);
    }
}
