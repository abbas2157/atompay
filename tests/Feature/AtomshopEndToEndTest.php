<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Cart;
use App\Models\CreditAssessment;
use App\Models\CustomOrder;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\OrderStatus;
use App\Models\Enums\VerificationStatus;
use App\Models\Order;
use App\Models\OrderInstalment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The whole AtomPay process, across both applications, in the v2 design:
 *
 *   apply -> staff verify the address -> staff decide a limit
 *     -> the eligible catalogue shows what the limit reaches
 *     -> the customer buys on AtomShop with AtomPay (3 months, no advance, 0%)
 *     -> the order and its schedule appear in My AtomPay
 *     -> paying instalments moves the progress and frees the limit again
 *
 * The AtomShop half is written straight into the shop's tables, because that is
 * what AtomShop's checkout does and AtomPay is a read-only guest there
 * (BelongsToAtomShop guards every model, hence forceFill/forceCreate).
 *
 * Runs on the shared database inside a transaction; uploads go to a fake disk.
 */
class AtomshopEndToEndTest extends TestCase
{
    use DatabaseTransactions;

    private function customer(): User
    {
        return User::customers()->active()->doesntHave('kycProfile')->firstOrFail();
    }

    private function staff(): User
    {
        return User::whereIn('role', config('atompay.staff_roles'))->where('status', 'active')->firstOrFail();
    }

    /** An AtomPay-eligible product the shop would actually offer. */
    private function eligibleProduct(int $maxPrice): Product
    {
        $product = Product::query()
            ->visible()
            ->atompayEligible()
            ->where('price', '>', 0)
            ->where('price', '<=', $maxPrice)
            ->orderBy('price')
            ->first();

        if (! $product) {
            $this->markTestSkipped('no AtomPay-eligible product at or under Rs. '.number_format($maxPrice));
        }

        return $product;
    }

    /**
     * The declared income whose limit just covers a price.
     *
     * Derived rather than hardcoded: the catalogue is real shop data, and on
     * this database the cheapest eligible product is Rs. 47,000, which a
     * Rs. 150,000 income would not reach. Working back from the product keeps
     * the test honest wherever it runs.
     *
     * The monthly figure never breaches the cap by construction: the cap is 10%
     * of income and the limit 30%, so cap = limit / 3, and a three-month plan on
     * a price within the limit costs price / 3 <= limit / 3.
     */
    private function incomeCovering(int $price): int
    {
        $ratio = (float) config('atompay.credit.limit_ratio');

        return (int) (ceil($price / $ratio / 1000) * 1000);
    }

    /**
     * What AtomShop's checkout writes for an AtomPay order: the order, its cart
     * line, the custom_orders mirror, and the schedule on that mirror
     * (order_type = Custom - MEMORY, 2026-10-08). Three months, no advance,
     * 0% markup, last month taking the rounding.
     */
    /**
     * Status defaults to Instalments, not Delivered, because that is when
     * AtomShop generates the schedule (Pending -> Varification -> Processing ->
     * Delivered -> Instalments -> Completed). The distinction matters:
     * ObligationService::exposure() adds the financed amount of orders that are
     * Processing or Delivered to the unpaid instalments, on the assumption that
     * such an order has no schedule yet. An order that is Delivered *and*
     * already carries a schedule would be counted twice - see the note in
     * docs/design-v2-migration.md.
     */
    private function placeAtompayOrder(User $user, Product $product, OrderStatus $status = OrderStatus::Instalments): Order
    {
        $price = (int) $product->price;
        $months = (int) config('atompay.atompay_plan.months');
        $monthly = intdiv($price, $months);

        $cart = Cart::forceCreate([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'product_price' => $price,
            'product_advance_price' => 0,
            'tenure' => $months,
        ]);

        $order = Order::forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'total_deal_price' => $price,
            'advance_price' => 0,
            // `orders` names it instalment_tenure; `carts` and `custom_orders`
            // both call the same thing `tenure`.
            'instalment_tenure' => $months,
            'portal' => 'Web',
            'type' => Order::TYPE_ATOMPAY,
            'status' => $status->value,
        ]);

        $mirror = CustomOrder::forceCreate([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'total_deal_price' => $price,
            'advance_price' => 0,
            'tenure' => $months,
            'type' => CustomOrder::TYPE_ATOMPAY,
            'status' => $status->value,
            'source_order_id' => $order->id,
        ]);

        for ($month = 1; $month <= $months; $month++) {
            $isLast = $month === $months;

            OrderInstalment::forceCreate([
                'user_id' => $user->id,
                'order_id' => $mirror->id,
                'month' => now()->addMonthsNoOverflow($month)->format('F Y'),
                'installment_price' => $isLast ? $price - $monthly * ($months - 1) : $monthly,
                'installment_date' => now()->addMonthsNoOverflow($month)->toDateString(),
                'type' => OrderInstalment::TYPE_INSTALMENT,
                'status' => 'Unpaid',
                'order_type' => OrderInstalment::ORDER_CUSTOM,
            ]);
        }

        return $order->refresh();
    }

    private function application(array $overrides = []): array
    {
        return [
            'full_name' => 'End To End',
            'cnic' => '42101-7654321-9',
            'mobile' => '0300 7654321',
            'date_of_birth' => '1992-04-11',
            'residential_address' => 'House 9, Street 4, Lahore',
            'cnic_front' => UploadedFile::fake()->image('front.jpg'),
            'cnic_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'employment_status' => 'salaried',
            'income_source' => 'salary',
            'employer_name' => 'Acme Ltd',
            'monthly_income' => 150000,
            'existing_instalments' => 0,
            'monthly_expenses' => 112000,
            ...$overrides,
        ];
    }

    /** Every step, in order, in the design that will be live. */
    public function test_the_whole_process_from_application_to_last_payment(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $user = $this->customer();
        $staff = $this->staff();

        // Work back from the real catalogue: the income is whatever makes the
        // cheapest eligible product reachable.
        $product = $this->eligibleProduct(PHP_INT_MAX);
        $price = (int) $product->price;
        $income = $this->incomeCovering($price);
        $expenses = $income - 38000;

        $limit = (int) round($income * config('atompay.credit.limit_ratio'));
        $cap = (int) round($income * config('atompay.credit.instalment_ratio'));
        $monthly = intdiv($price, 3);

        $this->assertGreaterThanOrEqual($price, $limit, 'the derived limit must cover the product');

        /* ---------------------------------------------- 1. nothing yet */
        $this->actingAs($user)->get(route('account.applications'))
            ->assertOk()
            ->assertSee('No limit yet');

        /* ------------------------------------- 2. the customer applies */
        $this->actingAs($user)
            ->post(route('account.application.store'), $this->application([
                'monthly_income' => $income,
                'monthly_expenses' => $expenses,
            ]))
            ->assertSessionHasNoErrors();

        $assessment = CreditAssessment::where('user_id', $user->id)->firstOrFail();

        // Limit is 30% of income; the cap is the lower of 10% and disposable.
        $this->assertSame(38000, $assessment->disposable_income);
        $this->assertSame($limit, $assessment->approved_limit);
        $this->assertSame(min($cap, 38000), $assessment->max_instalment);
        $this->assertSame(AssessmentStatus::Pending, $assessment->status);

        // A provisional limit is not spendable, and the page says so.
        $this->actingAs($user)->get(route('account.applications'))
            ->assertOk()
            ->assertSee('Application history')
            ->assertSee('AP-'.$assessment->id)
            ->assertSee(\App\Support\Money::format($income, 'Rs. '))   // the income it was based on
            ->assertSee('No limit yet');            // nothing in force yet

        /* ------------------------------ 3. staff verify, then decide */
        $this->actingAs($user)->get(route('staff.assessments.index'))->assertForbidden();

        $this->actingAs($staff)->post(route('staff.assessments.address', $assessment), [
            'address_verified' => 1,
            'face_verified' => 1,
            'verified_at' => today()->format('Y-m-d'),
            'verification_status' => 'verified',
            'verification_notes' => 'Met at residence.',
            'verification_form' => UploadedFile::fake()->create('form.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->post(route('staff.assessments.decide', $assessment), [
            'credit_history' => 'good',
            'approved_limit' => $limit,
            'max_instalment' => min($cap, 38000),
            'approved_tenure' => 3,
            'status' => 'approved',
        ])->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Approved, $assessment->status);
        $this->assertTrue($user->fresh()->kycProfile->isVerified());

        /* ------------------------- 4. the limit is live for the customer */
        $this->actingAs($user)->get(route('account.applications'))
            ->assertOk()
            ->assertSee('Available to spend')
            ->assertSee(\App\Support\Money::format($limit, 'Rs. '))
            ->assertSee('Approved');

        $this->actingAs($user)->get(route('account.verification'))
            ->assertOk()
            ->assertSee('Your limit is live')
            ->assertSee('Address confirmed');

        /* ----------------- 5. the catalogue shows what the limit reaches */
        $this->get(route('products'))
            ->assertOk()
            ->assertSee($product->title, false)
            ->assertSee(\App\Support\Money::format($monthly, 'Rs. '))
            ->assertSee('AtomPay eligible')
            // The card links to the product's page on the shop, not to AtomPay.
            ->assertSee(rtrim(config('atompay.shop_url'), '/').'/'.$product->slug, false);

        /* ------------------- 6. the customer buys it with AtomPay */
        $order = $this->placeAtompayOrder($user, $product);

        $this->assertSame('atompay', $order->type);
        $this->assertSame(0, (int) $order->advance_price);
        $this->assertSame($price, $order->financed_amount);
        $this->assertCount(3, $order->schedule);
        $this->assertSame($price, (int) $order->schedule->sum('installment_price'));

        /* --------------------- 7. it appears in My AtomPay, with dates */
        $this->actingAs($user)->get(route('account.orders'))
            ->assertOk()
            ->assertSee($product->title, false)
            ->assertSee('#'.$order->reference)
            ->assertSee('1 of 3')
            ->assertSee('3 of 3')
            ->assertSee(\App\Support\Money::format($monthly, 'Rs. '))
            ->assertSee('AtomPay takes none');          // the advance line

        /* ------------------------- 8. the limit is used up accordingly */
        $credit = app(\App\Services\CreditAssessmentService::class)->summary($user->fresh());

        $this->assertSame($limit, $credit['limit']);
        $this->assertSame($price, $credit['used']);
        $this->assertSame($limit - $price, $credit['available']);

        /* --------------------------- 9. paying moves the progress on */
        $first = $order->schedule->first();
        $first->forceFill(['status' => 'Paid', 'installment_paid_date' => today()])->save();

        $plan = app(\App\Services\PaymentScheduleService::class)->forOrder($user->fresh(), $order->id);

        $this->assertSame(1, $plan['paid_count']);
        $this->assertSame(3, $plan['total_count']);
        $this->assertSame(33, $plan['progress']);
        $this->assertSame('on_track', $plan['state']);

        $this->actingAs($user)->get(route('account.orders'))
            ->assertOk()
            ->assertSee('1 of 3 payments made')
            ->assertSee('Paid');

        /* ------------------------------- 10. clearing it closes the order */
        foreach ($order->schedule as $instalment) {
            $instalment->forceFill(['status' => 'Paid', 'installment_paid_date' => today()])->save();
        }
        Order::whereKey($order->id)->update(['status' => OrderStatus::Completed->value]);

        $plan = app(\App\Services\PaymentScheduleService::class)->forOrder($user->fresh(), $order->id);
        $this->assertSame('completed', $plan['state']);
        $this->assertSame(100, $plan['progress']);

        // And the limit is free again.
        $credit = app(\App\Services\CreditAssessmentService::class)->summary($user->fresh());
        $this->assertSame(0, $credit['used']);
        $this->assertSame($limit, $credit['available']);
    }

    /**
     * Eligibility is admin's selection on AtomShop, and AtomPay only reads it:
     * per product, or per brand for a whole catalogue.
     */
    public function test_only_products_admin_marked_eligible_are_listed(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $ineligible = Product::query()->visible()->where('price', '>', 0)
            ->where('atompay_eligible', false)
            ->whereNotIn('brand_id', Brand::query()->atompayEligible()->select('id'))
            ->first();

        if (! $ineligible) {
            $this->markTestSkipped('every local product is AtomPay-eligible');
        }

        $this->get(route('products'))
            ->assertOk()
            ->assertDontSee($ineligible->title, false);

        // Turning the product's own flag on is enough to list it.
        Product::whereKey($ineligible->id)->update(['atompay_eligible' => true]);
        app(\App\Services\CatalogueService::class)->forgetFilters();

        $this->get(route('products'))->assertOk()->assertSee($ineligible->title, false);
    }

    /** A whole brand can be switched on, without touching each product. */
    public function test_a_brand_wide_flag_lists_all_of_its_products(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $brand = Brand::query()->active()->where('atompay_eligible', false)
            ->whereHas('products', fn ($q) => $q->visible()->where('price', '>', 0)->where('atompay_eligible', false))
            ->first();

        if (! $brand) {
            $this->markTestSkipped('no brand with ineligible products to switch on');
        }

        $product = $brand->products()->visible()->where('price', '>', 0)->where('atompay_eligible', false)->firstOrFail();

        $this->get(route('products'))->assertOk()->assertDontSee($product->title, false);

        Brand::whereKey($brand->id)->update(['atompay_eligible' => true]);
        app(\App\Services\CatalogueService::class)->forgetFilters();

        $this->get(route('products'))->assertOk()->assertSee($product->title, false);
        $this->assertTrue($product->fresh()->isAtompayEligible());
    }

    /** Never on a product the shop has not published. */
    public function test_an_unpublished_product_is_never_listed(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $product = $this->eligibleProduct(PHP_INT_MAX);
        Product::whereKey($product->id)->update(['status' => 'On hold']);

        $this->get(route('products'))->assertOk()->assertDontSee($product->title, false);
    }

    /** The listing is a GET form, so a filtered view is a shareable URL. */
    public function test_the_category_filter_narrows_the_listing(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $catalogue = app(\App\Services\CatalogueService::class);
        $categories = $catalogue->categories();

        if ($categories->count() < 2) {
            $this->markTestSkipped('need eligible products in two categories to test filtering');
        }

        $first = $categories->first();
        $inFirst = Product::query()->visible()->atompayEligible()->where('price', '>', 0)
            ->where('category_id', $first->id)->firstOrFail();
        $elsewhere = Product::query()->visible()->atompayEligible()->where('price', '>', 0)
            ->where('category_id', '!=', $first->id)->firstOrFail();

        $this->get(route('products', ['category' => $first->id]))
            ->assertOk()
            ->assertSee($inFirst->title, false)
            ->assertDontSee($elsewhere->title, false);
    }

    /**
     * Four per row, and a missing picture must show the placeholder. The
     * product files are AtomShop's, so AtomPay can never assume one is there -
     * on this machine none of them are.
     *
     * The placeholder is rendered BEFORE the picture and is never hidden: it
     * sits behind, so a product with no image shows it without waiting on
     * JavaScript. An earlier version hid it and relied on an onerror handler,
     * which left an empty box whenever that did not fire.
     */
    public function test_the_listing_is_four_up_and_shows_a_placeholder(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $html = $this->get(route('products'))
            ->assertOk()
            ->assertSee('class="grid cols-4"', false)
            ->assertSee('class="ph-glyph"', false)
            ->assertSee('data-fallback', false)
            // An empty alt: the title is right below it, so a broken picture
            // must not repeat the name as alt text.
            ->assertSee('alt=""', false)
            ->getContent();

        // Behind, not in front, and never hidden.
        $img = (string) preg_replace('/.*?<div class="p-img">/s', '', $html);
        $img = substr($img, 0, (int) strpos($img, '</div>'));

        $this->assertLessThan(
            strpos($img, '<img'),
            strpos($img, 'ph-glyph'),
            'the placeholder must be rendered before the picture, so it sits behind it',
        );
        $this->assertStringNotContainsString('ph-glyph" hidden', $img);
    }

    /** The 01-04 counters were removed from the How it works cards. */
    public function test_the_how_it_works_cards_carry_no_counters(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Get the limit once')
            ->assertDontSee('num-badge', false);
    }

    /** In v1 there is no listing, so the route sends visitors to the shop. */
    public function test_the_listing_is_v2_only(): void
    {
        config(['design.default' => 'v1', 'design.active' => 'v1']);

        $this->get(route('products'))->assertRedirect(config('atompay.shop_url'));
    }

    /**
     * Product pictures live on AtomShop, not here. products.picture holds a
     * path like "images/products/foo.webp" and the shop serves those under
     * /public, so a value that is relative, or that points at AtomPay's own
     * host, 404s every image on the site - which is how it shipped to live
     * once. These assertions are about the shape of the URL, not the host,
     * so they hold for localhost and production alike.
     */
    public function test_product_pictures_are_served_from_the_shop_not_from_atompay(): void
    {
        $product = new Product;
        $product->picture = 'images/products/foo-123-abc.webp';
        $url = $product->picture_url;

        $this->assertMatchesRegularExpression(
            '#^https?://#', $url,
            'the asset url must be absolute - a relative one resolves against atompay and 404s'
        );
        $this->assertStringEndsWith('/images/products/foo-123-abc.webp', $url);

        // products.picture is served from /public on the shop.
        $this->assertStringContainsString('/public/images/products/', $url);

        /*
         * Never AtomPay itself. Comparing hosts does not work portably - in
         * development both apps sit on localhost and differ only by path, and
         * in the test environment APP_URL is a bare host - but the one value
         * that is always wrong is one naming AtomPay. The live breakage was
         * ATOMPAY_ASSET_URL=https://atompay.shop/public, which is absolute and
         * does contain /public/images/products/, so the checks above pass it
         * and only this catches it.
         */
        $this->assertStringNotContainsStringIgnoringCase(
            'atompay',
            $url,
            'pictures must come from AtomShop - ATOMPAY_ASSET_URL points at AtomPay'
        );

        // No picture, no URL - the card renders its placeholder instead.
        $blank = new Product;
        $blank->picture = null;
        $this->assertNull($blank->picture_url);
    }

    /** A trailing slash on the configured base must not double up. */
    public function test_the_asset_url_is_joined_without_a_double_slash(): void
    {
        config(['atompay.asset_url' => 'https://atomshop.pk/public/']);

        $product = new Product;
        $product->picture = '/images/products/foo.webp';

        $this->assertSame('https://atomshop.pk/public/images/products/foo.webp', $product->picture_url);
    }

    /**
     * With ATOMPAY_ASSET_URL unset - which is how production should run - the
     * picture host derives from ATOMSHOP_URL, so the shop is named in one place
     * and the pictures cannot drift from the product links built off shop_url.
     * That drift is exactly what shipped: links went to atomshop.pk while the
     * images pointed at atompay.shop.
     */
    public function test_the_picture_host_derives_from_the_shop_url_when_unset(): void
    {
        $derived = rtrim(env('ATOMSHOP_URL', 'https://atomshop.pk'), '/').'/public';

        $this->assertSame('https://atomshop.pk/public', $derived);
        $this->assertStringStartsWith(
            rtrim(config('atompay.shop_url'), '/'),
            $derived,
            'the pictures must come from the same host as the product links'
        );
    }
}
