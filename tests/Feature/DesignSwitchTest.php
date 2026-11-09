<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The v1/v2 design switch. v2 is additive: nothing was moved, so a page with
 * no v2 view keeps rendering its v1 view in either design, and flipping
 * ATOMPAY_DESIGN can never blank a page.
 *
 * Runs against the shared AtomShop database, so DatabaseTransactions - never
 * RefreshDatabase (see SiteTest).
 */
class DesignSwitchTest extends TestCase
{
    use DatabaseTransactions;

    private function customer(): User
    {
        return User::customers()->active()->firstOrFail();
    }

    private function staff(): User
    {
        return User::whereIn('role', config('atompay.staff_roles'))->where('status', 'active')->firstOrFail();
    }

    private function useDesign(string $design): void
    {
        config(['design.default' => $design, 'design.active' => $design]);
    }

    /** v2 has been the live design since 9 Nov 2026. */
    public function test_v2_is_the_default_and_renders_the_v2_home(): void
    {
        $this->assertSame('v2', config('design.default'));

        $this->get('/')
            ->assertOk()
            ->assertSee('web/v2/css/site.css', false)
            ->assertSee('Rs. 33,333')           // AtomPay's own plan
            ->assertDontSee('PKR 16,534');      // v1's seller-financing quote
    }

    /** v1 is still reachable, and still works, through a preview. */
    public function test_v1_still_renders_when_asked_for(): void
    {
        $this->useDesign('v1');

        $this->get('/')
            ->assertOk()
            ->assertSee('PKR 16,534')          // v1's seller-financing sample quote
            ->assertDontSee('web/v2/css/site.css', false);
    }

    public function test_v2_renders_the_v2_home_with_atompays_own_plan(): void
    {
        $this->useDesign('v2');

        $this->get('/')
            ->assertOk()
            ->assertSee('web/v2/css/site.css', false)
            ->assertSee('web/v2/css/atompay.css', false)
            // AtomPay's real rule, not seller financing.
            ->assertSee('Rs. 33,333')
            ->assertSee('No advance')
            ->assertSee('0% markup')
            ->assertDontSee('PKR 16,534');
    }

    /**
     * A page with no v2 view must fall back, not 500 and not go blank. The
     * staff review screens are the real case now that the application wizard
     * has been migrated: they are internal, so they stay on v1 chrome
     * deliberately (docs/design-v2-migration.md).
     */
    public function test_a_page_without_a_v2_view_falls_back_to_v1(): void
    {
        $this->assertFalse(
            view()->exists('v2.staff.assessments.index'),
            'the staff screens have a v2 view now - point this test at another unmigrated page, or delete it',
        );

        $this->useDesign('v2');
        $this->actingAs($this->staff());

        // Renders the v1 view, inside v1 chrome, without erroring.
        $this->get('/staff/assessments')->assertOk()->assertSee('noindex');
    }

    /** The customer-facing pages are all migrated; nothing falls back for them. */
    public function test_every_customer_facing_view_has_a_v2_twin(): void
    {
        foreach ([
            'home.index', 'pages.faq', 'pages.products', 'pages.privacy', 'pages.delete-account',
            'auth.login', 'auth.register', 'auth.register-verify', 'auth.verify-code',
            'auth.forgot-password', 'auth.reset-password',
            'account.dashboard', 'account.orders', 'account.verification',
            'account.applications', 'account.application', 'account.delete',
        ] as $view) {
            $this->assertTrue(view()->exists('v2.'.$view), "v2.{$view} is missing, so {$view} silently renders v1");
        }
    }

    /** design_view() returns the v1 name when there is no v2 twin. */
    public function test_design_view_falls_back_by_name(): void
    {
        $this->useDesign('v2');

        $this->assertSame('v2.home.index', design_view('home.index'));
        $this->assertSame('staff.assessments.index', design_view('staff.assessments.index'));

        $this->useDesign('v1');

        $this->assertSame('home.index', design_view('home.index'));
    }

    public function test_every_public_page_renders_in_both_designs(): void
    {
        foreach (['v1', 'v2'] as $design) {
            $this->useDesign($design);

            foreach (['/', '/faq', '/privacy', '/login', '/register'] as $path) {
                $this->get($path)->assertOk();
            }
        }
    }

    public function test_the_v2_account_pages_render_for_a_customer(): void
    {
        $this->useDesign('v2');

        $this->actingAs($this->customer());

        $this->get('/my/applications')->assertOk()->assertSee('My applications');
        $this->get('/my/verification')->assertOk()->assertSee('KYC &amp; verification', false);
        $this->get('/my/orders')->assertOk()->assertSee('My orders');
    }

    /**
     * The empty states are the easy half. This renders the populated branches -
     * a customer with a KYC profile, a decided assessment and real orders -
     * because that is where the schedules, trackers and documents live.
     */
    public function test_the_v2_account_pages_render_with_real_data(): void
    {
        $user = User::customers()->active()
            ->whereHas('kycProfile')
            ->whereHas('activeAssessment')
            ->first();

        if (! $user) {
            $this->markTestSkipped('no local customer has both a KYC profile and a decided limit');
        }

        $this->useDesign('v2');
        $this->actingAs($user);

        $this->get('/my/applications')
            ->assertOk()
            ->assertSee('Available to spend')
            ->assertSee('Application history')
            ->assertSee('The income this limit is based on')
            ->assertSee('Rs.');

        $this->get('/my/verification')
            ->assertOk()
            ->assertSee('Where you are')
            ->assertSee('Identity details')
            ->assertSee('Your documents')
            ->assertSee('Address verification');

        $this->get('/my/orders')->assertOk()->assertSee('Left of your limit');
    }

    /** An order with a schedule must render its track, progress bar and table. */
    public function test_the_orders_page_renders_a_real_payment_schedule(): void
    {
        $user = User::customers()->active()->whereHas('orders')->first();

        if (! $user) {
            $this->markTestSkipped('no local customer has an order');
        }

        $this->useDesign('v2');
        $this->actingAs($user);

        $this->get('/my/orders')
            ->assertOk()
            ->assertSee('Your Atomshop orders')
            ->assertSee('Cash price');
    }

    public function test_the_new_account_pages_are_never_indexed(): void
    {
        $this->useDesign('v2');
        $this->actingAs($this->customer());

        foreach (['/my/applications', '/my/verification', '/my/orders'] as $path) {
            $this->get($path)->assertOk()->assertSee('noindex');
        }
    }

    /**
     * The three-page split is a v2 idea - v1 shows all of it on one dashboard -
     * so in v1 these URLs go there instead of rendering a view that does not exist.
     */
    public function test_the_split_pages_redirect_to_the_dashboard_in_v1(): void
    {
        $this->useDesign('v1');
        $this->actingAs($this->customer());

        foreach (['/my/applications', '/my/verification', '/my/orders'] as $path) {
            $this->get($path)->assertRedirect(route('account.dashboard'));
        }
    }

    public function test_the_account_pages_need_a_signed_in_customer(): void
    {
        foreach (['/my/applications', '/my/verification', '/my/orders'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    /** ?design= sticks for the session, and the previewed page is noindexed. */
    public function test_a_preview_is_remembered_and_not_indexable(): void
    {
        // The preview is whichever design is not live, so now that is v1.
        $this->get('/?design=v1')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertDontSee('web/v2/css/site.css', false);

        // Still v1 on the next request, without the query string.
        $this->get('/')->assertOk()->assertDontSee('web/v2/css/site.css', false);

        // Back to the live design, and no longer a preview.
        $this->get('/?design=v2')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            ->assertSee('web/v2/css/site.css', false);
    }

    public function test_an_unknown_design_is_ignored(): void
    {
        $this->get('/?design=v9')
            ->assertOk()
            ->assertHeaderMissing('X-Robots-Tag')
            // Falls through to the live design rather than anything odd.
            ->assertSee('web/v2/css/site.css', false);
    }

    /** In production only staff may preview. */
    public function test_production_refuses_a_preview_to_a_guest(): void
    {
        app()->detectEnvironment(fn () => 'production');

        // A guest cannot drop the live site back to v1.
        $this->get('/?design=v1')->assertOk()->assertSee('web/v2/css/site.css', false);
    }

    /**
     * The v2 design adds Google Fonts to the CSP; v1 keeps the tighter
     * self-hosted policy. Checked here because a missing host silently loses
     * the whole type system in the browser, with no server-side error.
     */
    public function test_the_csp_allows_the_v2_web_font_only_in_v2(): void
    {
        $this->useDesign('v1');
        $v1 = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('fonts.gstatic.com', (string) $v1);

        $this->useDesign('v2');
        $v2 = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('fonts.googleapis.com', (string) $v2);
        $this->assertStringContainsString('fonts.gstatic.com', (string) $v2);
    }

    /** Every auth screen, in both designs. These are noindex and must stay so. */
    public function test_the_auth_pages_render_in_both_designs(): void
    {
        foreach (['v1', 'v2'] as $design) {
            $this->useDesign($design);

            $this->get('/login')->assertOk()->assertSee('noindex')->assertSee('Forgot password');
            $this->get('/register')->assertOk()->assertSee('noindex');
            $this->get('/forgot-password')->assertOk()->assertSee('noindex');
        }
    }

    public function test_the_v2_auth_pages_carry_the_shared_account_panel(): void
    {
        $this->useDesign('v2');

        foreach (['/login', '/register', '/forgot-password'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('One account, both sites')
                ->assertSee('3 months &middot; no advance &middot; 0% markup', false)
                // The same account signs in to the app, so the stores sit here too.
                ->assertSee('auth-apps', false)
                ->assertSee(config('atompay.api.store_url.android'), false)
                ->assertSee(config('atompay.api.store_url.ios'), false);
        }
    }

    /** A field's own message must sit next to the field, not only in a banner. */
    public function test_a_v2_form_shows_the_error_against_the_field(): void
    {
        $this->useDesign('v2');

        $this->from('/login')
            ->post('/login', ['login' => '', 'password' => ''])
            ->assertRedirect('/login');

        $this->useDesign('v2');
        $this->followingRedirects()
            ->from('/login')
            ->post('/login', ['login' => '', 'password' => ''])
            ->assertOk()
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('id="login-error"', false);
    }

    public function test_the_content_pages_render_in_both_designs(): void
    {
        foreach (['v1', 'v2'] as $design) {
            $this->useDesign($design);

            // The policy text is shared, so a section from it must appear in both.
            $this->get('/privacy')->assertOk()->assertSee('Who we share it with');
            $this->get('/delete-account')->assertOk()->assertSee('How to delete it');
            $this->get('/faq')->assertOk()->assertSee('FAQPage');
        }
    }

    /**
     * Error pages share their words and swap only the shell. Checked in both
     * designs because each shell is hand-maintained and self-contained.
     */
    public function test_error_pages_render_in_both_designs(): void
    {
        foreach (['v1' => 'installment payments', 'v2' => 'the installment limit'] as $design => $footer) {
            $this->useDesign($design);

            $response = $this->get('/this-route-does-not-exist');

            $response->assertNotFound()
                // The shared copy. Blade escapes the apostrophe, so match around it.
                ->assertSee('find that page', false)
                ->assertSee('noindex')
                ->assertSee($footer);                            // the design's own shell
        }
    }

    /**
     * An unmatched URL never reaches the `web` group, so SetDesign does not run
     * for a 404 and there is no session to read a ?design= preview from. The
     * error page must still follow the LIVE design, which is what visitors and
     * crawlers see - so design() falls back to config('design.default') with
     * design.active unset. Pinned here because the failure is silent: the page
     * renders fine, just in the wrong design.
     */
    public function test_error_pages_follow_the_live_design_without_the_middleware(): void
    {
        config(['design.default' => 'v2']);
        config()->offsetUnset('design.active');

        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertSee('the installment limit')     // v2 shell
            ->assertDontSee('installment payments');  // v1 shell

        config(['design.default' => 'v1']);
        config()->offsetUnset('design.active');

        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertSee('installment payments');
    }

    public function test_the_v2_error_shell_links_no_stylesheet(): void
    {
        $this->useDesign('v2');

        // A 500 that linked site.css would be unstyled the one time it matters.
        $this->get('/this-route-does-not-exist')
            ->assertNotFound()
            ->assertDontSee('web/v2/css/site.css', false)
            ->assertDontSee('fonts.googleapis.com', false);
    }

    /**
     * Emails render with no request in front of them, so they follow the live
     * design rather than a preview - design_view() reads config, not session.
     */
    public function test_emails_use_the_shell_of_the_live_design(): void
    {
        $data = ['name' => 'Ayesha', 'code' => '123456', 'minutes' => 10];

        config(['design.default' => 'v1', 'design.active' => 'v1']);
        $v1 = view(design_view('emails.password-code'), $data)->render();
        $this->assertStringContainsString('#FBFAF7', $v1);
        $this->assertStringNotContainsString('ATOMPAY', $v1);

        config(['design.default' => 'v2', 'design.active' => 'v2']);
        $v2 = view(design_view('emails.password-code'), $data)->render();

        $this->assertStringContainsString('#F5F7FB', $v2);          // v2 surface
        $this->assertStringContainsString('PAY IN STEPS', $v2);      // v2 lockup
        $this->assertStringContainsString('123456', $v2);            // the code still
        $this->assertStringNotContainsString('<img', $v2);           // no images in mail
    }

    public function test_the_overview_and_delete_pages_render_in_both_designs(): void
    {
        $this->actingAs($this->customer());

        foreach (['v1', 'v2'] as $design) {
            $this->useDesign($design);

            $this->get('/my')->assertOk()->assertSee('noindex');
            $this->get('/my/delete')->assertOk()->assertSee('noindex');
        }
    }

    /** The private areas stay out of robots.txt and the sitemap in both designs. */
    public function test_seo_files_do_not_leak_the_new_pages(): void
    {
        $this->useDesign('v2');

        // Outside production nothing is indexable at all, so a staging or local
        // copy can never compete with the real site.
        $robots = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertSame("User-agent: *\nDisallow: /\n", $robots);

        // In production the private areas are listed by name, /my among them,
        // which covers the three pages added for v2.
        app()->detectEnvironment(fn () => 'production');
        $live = $this->get('/robots.txt')->assertOk()->getContent();
        $this->assertStringContainsString('Disallow: /my', $live);
        $this->assertStringContainsString('Sitemap: ', $live);
        app()->detectEnvironment(fn () => 'testing');

        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        foreach (['/my/applications', '/my/verification', '/my/orders', '/login'] as $private) {
            $this->assertStringNotContainsString($private, $sitemap);
        }
        // Every public, indexable page is listed - including the two that were
        // missing before launch: the catalogue, and the delete-account page the
        // app store listings link straight to.
        foreach ([
            route('home'), route('faq'), route('products'),
            route('privacy'), route('account.delete.info'),
        ] as $public) {
            $this->assertStringContainsString('<loc>'.$public.'</loc>', $sitemap);
        }
    }

    /**
     * The two v1 landing sections, carried into v2: "Estimate a plan" and
     * "Get approved". Both are live in v2 rather than static examples, and both
     * keep v1's anchor ids so old links and AssessmentController's redirect
     * still land on them.
     */
    public function test_v2_carries_the_estimate_and_approval_sections(): void
    {
        $this->useDesign('v2');

        $response = $this->get('/')->assertOk();

        $response->assertSee('Estimate a plan')
            ->assertSee('See roughly what a monthly payment looks like.')
            ->assertSee('confirmed at Atomshop checkout once you', false)
            ->assertSee('Get approved')
            ->assertSee('Find out your limit before you shop.');

        // Live, not static: the hooks site.js reads. The price is a field plus
        // the artifact's three quick prices - it has no slider.
        $response->assertSee('data-plan-calc', false)
            ->assertSee('data-plan-price', false)
            ->assertSee('data-plan-set', false)
            ->assertSee('data-limit-calc', false)
            ->assertSee('data-limit-income', false);

        // v1's anchors, kept alive inside v2's own sections.
        $response->assertSee('id="calculator"', false)
            ->assertSee('id="assess"', false);
    }

    /** The schedule is server-rendered first, so it is right before any JS runs. */
    public function test_the_plan_estimator_is_rendered_before_javascript(): void
    {
        $this->useDesign('v2');

        $this->get('/')
            ->assertOk()
            ->assertSee('Rs. 33,333')   // each month on the Rs. 100,000 example
            ->assertSee('Rs. 33,334')   // the month that takes the rounding
            ->assertSee('Takes the rounding');
    }

    /**
     * The whole estimator round trip: post an income, the estimate is kept in
     * session, and v2 shows it back. AssessmentController redirects to #assess,
     * which only exists because v2 keeps that anchor.
     */
    public function test_an_income_estimate_survives_the_redirect_into_v2(): void
    {
        $this->useDesign('v2');

        $this->post(route('assess'), ['monthly_income' => 200000])
            ->assertRedirect(route('home').'#assess')
            ->assertSessionHas('atompay.estimate');

        $this->useDesign('v2');
        $this->get('/')
            ->assertOk()
            ->assertSee('We kept your estimate of')
            ->assertSee('Rs. 60,000');   // 30% of 200,000
    }

    /**
     * The limit card carries the same two "on top" lines the plan does, so the
     * processing fee and delivery are stated wherever a figure is quoted.
     */
    public function test_the_limit_card_states_the_fee_and_delivery(): void
    {
        $this->useDesign('v2');

        $fee = (float) config('atompay.atompay_plan.processing_fee_percent');
        $onFullLimit = \App\Support\Money::share(
            \App\Support\Money::share(
                (int) config('atompay.atompay_plan.example_income'),
                (float) config('atompay.credit.limit_ratio'),
            ),
            $fee / 100,
        );

        $this->get('/')
            ->assertOk()
            ->assertSee('Processing fee, '.$fee.'%')
            ->assertSee(\App\Support\Money::format($onFullLimit, 'Rs. '))
            ->assertSee('Charges apply')
            // A short phrase: the sentence wraps in the Blade, so a longer one
            // would span a newline and never match.
            ->assertSee('added to the order total')
            // The badge and note swap once the visitor's own income drives it.
            ->assertSee('data-limit-badge', false)
            ->assertSee('data-limit-note', false)
            ->assertSee('data-fee-percent', false);
    }

    /**
     * Every ecosystem card carries its own app's store links. The links mirror
     * AtomShop's config('website.app.*') - the shop is the authority, because
     * its /apps page and api/app/config serve the same ones.
     */
    public function test_each_ecosystem_card_links_its_app(): void
    {
        $this->useDesign('v2');

        $response = $this->get('/')->assertOk();

        foreach ([
            config('atompay.apps.atomshop.android'),
            config('atompay.apps.atomshop.ios'),
            config('atompay.api.store_url.android'),
            config('atompay.api.store_url.ios'),
            config('atompay.apps.atomseller.android'),
            config('atompay.apps.atomseller.ios'),
            config('atompay.apps.atombrand.ios'),
        ] as $url) {
            $this->assertNotEmpty($url);
            $response->assertSee($url, false);
        }

        // Four cards, four link rows.
        $this->assertSame(4, substr_count($response->getContent(), 'class="eco-apps"'));
    }

    /**
     * AtomBrand has no Android build published, so the card must say so rather
     * than render a link to nothing.
     */
    public function test_an_unpublished_app_says_soon_instead_of_linking(): void
    {
        config(['atompay.apps.atombrand.android' => null]);
        $this->useDesign('v2');

        $this->get('/')->assertOk()->assertSee('Android soon');
    }

    /**
     * The plan and account schedules sit in half a .split, where site.css's
     * 640px minimum on table.t forced a horizontal scrollbar that hid the last
     * column. Both carry .tight, which drops the minimum.
     */
    public function test_the_narrow_schedules_do_not_scroll_sideways(): void
    {
        $this->useDesign('v2');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(
            substr_count($html, '<table class="t'),
            substr_count($html, '<table class="t tight"'),
            'every table in a narrow column must be .tight, or it scrolls sideways',
        );
    }

    public function test_the_estimator_validates_the_income(): void
    {
        foreach ([null, 0, 500, 'abc'] as $bad) {
            $this->post(route('assess'), ['monthly_income' => $bad])
                ->assertSessionHasErrors('monthly_income');
        }
    }

    /**
     * No list renders browser markers.
     *
     * site.css does not reset list markers - it only clears .ticks and a few
     * SEO lists - so a plain <ol> rendered "1. 2. 3. 4." in the margin beside
     * the How it works cards. Those numbers came from CSS, so they never
     * appeared in the HTML and no amount of searching the markup found them.
     * The approved artifact carried the reset in its own base; this pins it.
     */
    public function test_no_list_renders_browser_markers(): void
    {
        $css = file_get_contents(public_path('web/v2/css/atompay.css'));

        $this->assertStringContainsString('ul,ol{margin:0;padding:0;list-style:none}', $css);

        // Long-form copy keeps its bullets: .prose ul is the more specific rule.
        $this->assertStringContainsString('.prose ul{margin:0 0 16px;padding-left:20px;list-style:disc}', $css);

        // And nothing numbers itself through a CSS counter either.
        foreach ([$css, file_get_contents(public_path('web/v2/css/site.css'))] as $sheet) {
            $this->assertDoesNotMatchRegularExpression('/content:\s*counter/', $sheet);
            $this->assertDoesNotMatchRegularExpression('/list-style-type:\s*decimal/', $sheet);
        }
    }

    public function test_the_v2_assets_exist_on_disk(): void
    {
        foreach (array_merge(config('design.assets.css'), config('design.assets.js')) as $asset) {
            $this->assertFileExists(public_path($asset));
        }

        $this->assertFileExists(public_path('web/v2/img/atompay-mark.png'));
    }
}
