<?php

namespace Tests\Feature\Api;

use App\Mail\KeyFactStatementMail;
use App\Mail\PasswordChangedMail;
use App\Models\Area;
use App\Models\Complaint;
use App\Models\Product;
use App\Services\CompanySettings;
use App\Services\KeyFactStatementService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * The endpoints the v2 app design needs beyond v1: launch config additions,
 * areas, the eligible catalogue and plan preview, the Key Fact Statement,
 * complaints, Ask Atom and change password. Contract: docs/api/.
 */
class AppScreensApiTest extends ApiTestCase
{
    /* ------------------------------------------------------------ app-config */

    public function test_app_config_carries_maintenance_apps_and_voice(): void
    {
        Config::set('atompay.api.maintenance', ['active' => true, 'message' => 'Back soon', 'until' => '2026-11-10 16:30:00']);
        Config::set('atompay.api.voice', false);

        $this->getJson('/api/v1/app-config')
            ->assertOk()
            ->assertJsonPath('data.maintenance.active', true)
            ->assertJsonPath('data.maintenance.message', 'Back soon')
            ->assertJsonPath('data.features.voice', false)
            ->assertJsonPath('data.apps.atombrand.android', null)               // not on Play yet: the app says "Android soon"
            ->assertJsonPath('data.apps.atomseller.web', rtrim(config('atompay.shop_url'), '/').'/smart-seller')
            ->assertJsonStructure(['data' => ['maintenance' => ['until'], 'apps' => ['atomshop' => ['android', 'ios', 'web'], 'atompay', 'atomseller', 'atombrand']]]);

        $this->assertStringStartsWith('2026-11-10T16:30:00', $this->getJson('/api/v1/app-config')->json('data.maintenance.until'));
    }

    public function test_a_bad_maintenance_time_does_not_break_launch(): void
    {
        Config::set('atompay.api.maintenance.until', 'whenever');

        $this->getJson('/api/v1/app-config')->assertOk()->assertJsonPath('data.maintenance.until', null);
    }

    public function test_app_config_business_has_every_key_null_until_set(): void
    {
        Config::set('atompay.company', array_fill_keys(CompanySettings::KEYS, null));
        Config::set('atompay.api.support', ['phone' => '', 'whatsapp' => '   ', 'email' => null]);
        $this->companySettings([]);

        $this->getJson('/api/v1/app-config')->assertOk()
            ->assertJsonPath('data.business', [
                'company_name' => null, 'registered_address' => null, 'data_hosting' => null,
                'late_payment' => null, 'early_settlement' => null, 'complaint_response_time' => null,
            ])
            ->assertJsonPath('data.support', ['phone' => null, 'whatsapp' => null, 'email' => null]);   // never ""
    }

    public function test_app_config_business_comes_from_atomshop_settings_then_env(): void
    {
        Config::set('atompay.company', [...array_fill_keys(CompanySettings::KEYS, null),
            'company_name' => 'From Env (Pvt) Ltd', 'late_payment' => 'From env', 'data_hosting' => 'Env hosting']);
        Config::set('atompay.api.support', ['phone' => '021 111 222 333', 'whatsapp' => null, 'email' => 'help@example.test']);
        $this->companySettings([
            'company_name' => 'Atom Retail (Pvt) Ltd',              // the admin wins over .env
            'registered_address' => 'Plot 1, Karachi',
            'data_hosting' => '   ',                                // blank: falls back to .env
            'early_settlement' => 'No charge for paying early.',
            'complaint_response_time' => '2 working days',
        ]);

        $this->getJson('/api/v1/app-config')->assertOk()
            ->assertJsonPath('data.business', [
                'company_name' => 'Atom Retail (Pvt) Ltd',
                'registered_address' => 'Plot 1, Karachi',
                'data_hosting' => 'Env hosting',
                'late_payment' => 'From env',                       // not saved in the admin: .env
                'early_settlement' => 'No charge for paying early.',
                'complaint_response_time' => '2 working days',
            ])
            ->assertJsonPath('data.support', ['phone' => '021 111 222 333', 'whatsapp' => null, 'email' => 'help@example.test']);
    }

    public function test_company_settings_are_cached_for_a_minute(): void
    {
        Config::set('atompay.company', array_fill_keys(CompanySettings::KEYS, null));
        $this->companySettings(['company_name' => 'Old Name Ltd']);
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.business.company_name', 'Old Name Ltd');

        // Atomshop's admin saves a new name; Atomshop cannot clear AtomPay's cache.
        DB::table('atom_settings')->where('key', 'atompay.company_name')->update(['value' => 'New Name Ltd']);

        $this->travel(30)->seconds();
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.business.company_name', 'Old Name Ltd');
        $this->travel(31)->seconds();
        $this->getJson('/api/v1/app-config')->assertJsonPath('data.business.company_name', 'New Name Ltd');
    }

    public function test_options_list_complaint_topics(): void
    {
        $this->getJson('/api/v1/options')
            ->assertOk()
            ->assertJsonPath('data.complaint_topics.0', ['value' => 'payment', 'label' => 'A payment']);
    }

    /* ----------------------------------------------------------------- areas */

    public function test_areas_of_a_city_sorted_and_profile_returns_the_area(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);
        $area = Area::query()->where('city_id', 1)->where('status', 'active')->orderBy('title')->first();

        $titles = $this->withToken($token)->getJson('/api/v1/areas?city_id=1')->assertOk()->json('data.*.title');
        $this->assertSame($area->title, $titles[0]);
        $this->assertSame(
            Area::query()->where('city_id', 1)->where('status', 'active')->orderBy('title')->pluck('title')->all(),
            $titles,
        );

        $this->freshRequest()->withToken($token)->getJson('/api/v1/areas')->assertStatus(422)->assertJsonValidationErrors('city_id');
        $this->freshRequest()->withToken($token)->getJson('/api/v1/areas?city_id=999999')->assertStatus(422);
        $this->freshRequest()->withoutToken()->getJson('/api/v1/areas?city_id=1')->assertStatus(401);

        $profile = $this->makeProfile($user);
        $profile->update(['city_id' => 1, 'area_id' => $area->id]);
        $this->freshRequest()->withToken($token)->getJson('/api/v1/profile')
            ->assertJsonPath('data.area', ['id' => $area->id, 'title' => $area->title]);
    }

    /* -------------------------------------------------- products and preview */

    public function test_plan_preview_is_atompays_three_month_plan(): void
    {
        $this->getJson('/api/v1/plan-preview?price=47000')
            ->assertOk()
            ->assertJsonPath('data.advance', 0)
            ->assertJsonPath('data.markup', 0)
            ->assertJsonPath('data.months', 3)
            ->assertJsonPath('data.instalments', [15666, 15666, 15668])
            ->assertJsonPath('data.total', 47000)
            ->assertJsonPath('data.processing_fee', 1410)
            ->assertJsonPath('data.fits_limit', null);                    // signed out

        $this->getJson('/api/v1/plan-preview')->assertStatus(422);
    }

    public function test_plan_preview_says_whether_it_fits_the_signed_in_limit(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);
        $this->makeDecidedAssessment($user, 'approved', 60000);
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/plan-preview?price=47000')->assertJsonPath('data.fits_limit', true);
        $this->freshRequest()->withToken($token)->getJson('/api/v1/plan-preview?price=70000')->assertJsonPath('data.fits_limit', false);
    }

    public function test_products_are_eligible_cards_with_the_plan(): void
    {
        $eligible = Product::query()->visible()->atompayEligible()->where('price', '>', 0)->count();
        $this->assertGreaterThan(0, $eligible, 'The shared database needs at least one eligible product.');

        $response = $this->getJson('/api/v1/products')->assertOk()
            ->assertJsonPath('meta.total', $eligible)
            ->assertJsonStructure(['data' => [['id', 'title', 'brand', 'category', 'price', 'monthly', 'last_monthly', 'months', 'image_url', 'url', 'in_stock', 'within_limit']], 'meta' => ['current_page', 'last_page', 'total']]);

        $card = $response->json('data.0');
        $this->assertSame(intdiv($card['price'], 3), $card['monthly']);
        $this->assertSame($card['price'] - 2 * $card['monthly'], $card['last_monthly']);
        $this->assertNull($card['within_limit']);
        $this->assertArrayNotHasKey('plan', $card);

        $this->getJson('/api/v1/products/'.$card['id'])->assertOk()
            ->assertJsonPath('data.plan.instalments', [$card['monthly'], $card['monthly'], $card['last_monthly']]);

        $hidden = Product::query()->where('atompay_eligible', 0)->whereDoesntHave('brand', fn ($q) => $q->where('atompay_eligible', 1))->value('id');
        if ($hidden) {
            $this->getJson('/api/v1/products/'.$hidden)->assertNotFound();
        }
    }

    public function test_products_filter_by_category_search_and_limit(): void
    {
        $category = $this->getJson('/api/v1/products/categories')->assertOk()->json('data.0');
        $this->assertGreaterThan(0, $category['count']);

        $this->getJson('/api/v1/products?category='.$category['id'])->assertOk()->assertJsonPath('meta.total', $category['count']);

        $title = $this->getJson('/api/v1/products')->json('data.0.title');
        $words = array_reverse(explode(' ', $title));                      // any order still matches
        $this->assertContains($title, $this->getJson('/api/v1/products?q='.urlencode(implode(' ', $words)))->json('data.*.title'));
        $this->getJson('/api/v1/products?q=zzzz-no-such-product')->assertOk()->assertJsonCount(0, 'data');

        // Signed out, or no limit: "within my limit" is empty rather than everything.
        $this->getJson('/api/v1/products?within_limit=1')->assertOk()->assertJsonCount(0, 'data');

        $user = $this->makeCustomer();
        $this->makeProfile($user);
        $this->makeDecidedAssessment($user, 'approved', 60000);
        $prices = $this->withToken($this->tokenFor($user))->getJson('/api/v1/products?within_limit=1')->assertOk()->json('data.*.price');
        foreach ($prices as $price) {
            $this->assertLessThanOrEqual(60000, $price);
        }
    }

    /* --------------------------------------------------- Key Fact Statement */

    public function test_kfs_of_an_atompay_order(): void
    {
        Config::set('atompay.company', array_fill_keys(CompanySettings::KEYS, null));
        $this->companySettings([]);
        $user = $this->makeCustomer();
        $order = $this->makeAtomPayOrder($user, [['2026-12-10', 2666, true], ['2027-01-10', 2666, false], ['2027-02-10', 2668, false]]);

        $this->withToken($this->tokenFor($user))->getJson("/api/v1/plans/{$order}/kfs")
            ->assertOk()
            ->assertJsonPath('data.order_number', 'AS-'.str_pad((string) $order, 5, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.amount_financed', 8000)
            ->assertJsonPath('data.schedule_final', true)
            ->assertJsonPath('data.instalments.0', ['number' => 1, 'due_date' => '2026-12-10', 'amount' => 2666])
            ->assertJsonPath('data.total_repayable', 8000)
            ->assertJsonPath('data.processing_fee', 240)
            ->assertJsonPath('data.apr_percent', 18.5)
            ->assertJsonPath('data.lender', ['name' => null, 'licence' => null, 'address' => null])   // never invented
            ->assertJsonPath('data.late_payment', null)
            ->assertJsonPath('data.early_settlement', null)
            ->assertJsonPath('data.complaints', 'In the app: Help › Complaints.');
    }

    public function test_kfs_names_the_company_from_atomshop_settings_and_never_a_licence(): void
    {
        Config::set('atompay.company', [...array_fill_keys(CompanySettings::KEYS, null), 'late_payment' => 'Rs. 500 per late month.']);
        $this->companySettings([
            'company_name' => 'Atom Retail (Pvt) Ltd',
            'registered_address' => 'Plot 1, Karachi',
            'early_settlement' => 'No charge for paying early.',
            'complaint_response_time' => '2 working days',
        ]);
        $user = $this->makeCustomer();
        $order = $this->makeAtomPayOrder($user, [['2026-12-10', 2666, false]]);

        $this->withToken($this->tokenFor($user))->getJson("/api/v1/plans/{$order}/kfs")->assertOk()
            ->assertJsonPath('data.lender', ['name' => 'Atom Retail (Pvt) Ltd', 'licence' => null, 'address' => 'Plot 1, Karachi'])
            ->assertJsonPath('data.late_payment', 'Rs. 500 per late month.')          // .env fallback
            ->assertJsonPath('data.early_settlement', 'No charge for paying early.')
            ->assertJsonPath('data.complaints', 'In the app: Help › Complaints. We reply within 2 working days.');
    }

    public function test_kfs_before_the_schedule_exists_uses_the_plan_without_dates(): void
    {
        $user = $this->makeCustomer();
        $order = $this->makeAtomPayOrder($user, [], 'Pending');

        $this->withToken($this->tokenFor($user))->getJson("/api/v1/plans/{$order}/kfs")
            ->assertOk()
            ->assertJsonPath('data.schedule_final', false)
            ->assertJsonPath('data.instalments.2', ['number' => 3, 'due_date' => null, 'amount' => 2668]);
    }

    public function test_kfs_is_only_for_your_own_atompay_orders(): void
    {
        $user = $this->makeCustomer();
        $sellerFinanced = $this->makeOrder($user, [[now()->addMonth(), 10000, false]]);
        $someoneElses = $this->makeAtomPayOrder($this->makeCustomer(), [[now()->addMonth(), 1000, false]]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson("/api/v1/plans/{$sellerFinanced}/kfs")->assertNotFound();
        $this->freshRequest()->withToken($token)->getJson("/api/v1/plans/{$someoneElses}/kfs")->assertNotFound();
    }

    public function test_kfs_copy_by_email(): void
    {
        Mail::fake();
        $user = $this->makeCustomer();
        $order = $this->makeAtomPayOrder($user, [['2026-12-10', 2666, false]]);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson("/api/v1/plans/{$order}/kfs/send", ['channel' => 'email'])->assertStatus(202);
        Mail::assertSent(KeyFactStatementMail::class, fn ($m) => $m->hasTo($user->email) && $m->kfs['order_id'] === $order);

        $this->freshRequest()->withToken($token)->postJson("/api/v1/plans/{$order}/kfs/send", ['channel' => 'sms'])->assertStatus(422);
    }

    public function test_apr_is_zero_without_a_fee(): void
    {
        $this->assertSame(0.0, KeyFactStatementService::apr(47000, 0, [15666, 15666, 15668]));
        $this->assertSame(18.5, KeyFactStatementService::apr(47000, 1410, [15666, 15666, 15668]));
    }

    /* ------------------------------------------------------------ complaints */

    public function test_raise_and_list_a_complaint(): void
    {
        Mail::fake();
        $user = $this->makeCustomer();
        $order = $this->makeAtomPayOrder($user, [['2026-12-10', 2666, false]]);
        $token = $this->tokenFor($user);

        $created = $this->withToken($token)->postJson('/api/v1/complaints', [
            'topic' => 'payment', 'order_id' => $order, 'message' => 'My December payment is not showing as paid.',
        ])->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.topic_label', 'A payment')
            ->assertJsonPath('data.order_number', 'AS-'.str_pad((string) $order, 5, '0', STR_PAD_LEFT))
            ->json('data');

        $this->assertSame(Complaint::referenceFor($created['id']), $created['reference']);
        $this->assertMatchesRegularExpression('/^CMP-\d{4,}$/', $created['reference']);

        $this->freshRequest()->withToken($token)->getJson('/api/v1/complaints')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $created['reference']);
    }

    public function test_a_complaint_cannot_name_someone_elses_order(): void
    {
        $user = $this->makeCustomer();
        $theirs = $this->makeAtomPayOrder($this->makeCustomer(), [['2026-12-10', 1000, false]]);

        $this->withToken($this->tokenFor($user))->postJson('/api/v1/complaints', [
            'topic' => 'order', 'order_id' => $theirs, 'message' => 'Where is my delivery please?',
        ])->assertStatus(422)->assertJsonValidationErrors(['order_id']);

        $this->freshRequest()->withToken($this->tokenFor($user))->postJson('/api/v1/complaints', ['topic' => 'nonsense', 'message' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors(['topic', 'message']);
        $this->assertSame(0, Complaint::query()->where('user_id', $user->id)->count());
    }

    /* -------------------------------------------------------------- Ask Atom */

    public function test_ask_atom_answers_in_app_shape(): void
    {
        $this->getJson('/api/v1/ask-atom/chips')->assertOk()->assertJsonPath('data.0', 'What does AtomPay cost?');

        $answer = $this->postJson('/api/v1/ask-atom', ['question' => 'Plan for 47,000'])->assertOk()->json('data');

        $this->assertStringContainsString('**Rs. 15,666 a month for 3 months**', $answer['paragraphs'][0]);
        foreach ($answer['paragraphs'] as $p) {
            $this->assertStringNotContainsString('<', $p);
            $this->assertStringNotContainsString('&', $p);
        }
        $this->assertSame([['label' => 'Month 1', 'value' => 15666], ['label' => 'Month 2', 'value' => 15666], ['label' => 'Month 3', 'value' => 15668]], $answer['table']);
        $this->assertSame(['label' => 'Products near this price', 'route' => '/shop', 'url' => null], $answer['actions'][0]);
    }

    public function test_ask_atom_lists_become_bullets_and_signed_in_links_go_to_app_screens(): void
    {
        $answer = $this->postJson('/api/v1/ask-atom', ['question' => 'How do I apply?'])->json('data');
        $this->assertStringStartsWith('• ', $answer['paragraphs'][1]);
        $this->assertSame('/register', $answer['actions'][0]['route']);      // signed out

        $user = $this->makeCustomer();
        $this->freshRequest()->withToken($this->tokenFor($user))->postJson('/api/v1/ask-atom', ['question' => 'How do I apply?'])
            ->assertJsonPath('data.actions.0.route', '/application');

        $this->postJson('/api/v1/ask-atom', ['question' => ''])->assertStatus(422);
    }

    /* -------------------------------------------------------- change password */

    public function test_change_password_keeps_this_phone_and_signs_out_the_rest(): void
    {
        Mail::fake();
        $user = $this->makeCustomer();
        $mine = $this->tokenFor($user);
        $other = $this->tokenFor($user);

        $this->withToken($mine)->postJson('/api/v1/me/password', [
            'current_password' => self::PASSWORD, 'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('a-new-password-1', $user->fresh()->password));
        $this->assertSame(1, DB::table('atompay_personal_access_tokens')->where('tokenable_id', $user->id)->count());
        $this->freshRequest()->withToken($mine)->getJson('/api/v1/me')->assertOk();
        $this->freshRequest()->withToken($other)->getJson('/api/v1/me')->assertUnauthorized();
        Mail::assertSent(PasswordChangedMail::class, fn ($m) => $m->keptThisDevice);
    }

    public function test_change_password_needs_the_current_one(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/me/password', [
            'current_password' => 'wrong', 'password' => 'a-new-password-1', 'password_confirmation' => 'a-new-password-1',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->freshRequest()->withToken($token)->postJson('/api/v1/me/password', [
            'current_password' => self::PASSWORD, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD,
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check(self::PASSWORD, $user->fresh()->password));
    }
}
