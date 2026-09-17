<?php

namespace Tests\Feature;

use App\Models\CreditAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Runs against the shared AtomShop database. Never use RefreshDatabase
 * here - DatabaseTransactions rolls back anything a test writes.
 */
class SiteTest extends TestCase
{
    use DatabaseTransactions;

    private function customer(): User
    {
        return User::customers()->active()->firstOrFail();
    }

    public function test_home_renders_with_seo_and_structured_data(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"HowTo"', false)
            ->assertSee('PKR 16,534'); // server-rendered sample quote
    }

    public function test_public_pages_and_sitemap(): void
    {
        $this->get('/faq')->assertOk()->assertSee('FAQPage');
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->get('/login')->assertOk()->assertSee('noindex');
    }

    public function test_quote_endpoint_returns_server_math(): void
    {
        $this->postJson('/quote', ['price' => 100000, 'months' => 6])
            ->assertOk()
            ->assertJsonPath('quote.total', 119200);

        $this->postJson('/quote', ['price' => 100000, 'months' => 6, 'advance' => 1])
            ->assertStatus(422);
    }

    public function test_guest_estimate_is_kept_in_session_not_recorded(): void
    {
        $before = CreditAssessment::count();

        $this->post('/assess', ['monthly_income' => 150000])
            ->assertRedirect(route('home').'#assess')
            ->assertSessionHas('atompay.estimate.approved_limit', 45000);

        $this->assertSame($before, CreditAssessment::count());
    }

    public function test_signed_in_estimate_leads_to_the_full_application(): void
    {
        $user = $this->customer();

        $this->actingAs($user)->post('/assess', ['monthly_income' => 200000])
            ->assertRedirect(route('account.application'));

        // Nothing is recorded from income alone - the application does that.
        $this->assertSame(0, CreditAssessment::where('user_id', $user->id)->count());

        $this->actingAs($user)->get(route('account.application'))
            ->assertOk()->assertSee('Personal details')->assertSee('monthly_income\u0022:200000', false);
    }

    public function test_dashboard_requires_customer_account(): void
    {
        $this->get('/my')->assertRedirect('/login');

        $this->actingAs($this->customer())->get('/my')
            ->assertOk()
            ->assertSee('Your approved limit')
            ->assertSee('noindex');

        $staff = User::where('role', 'seller')->where('status', 'active')->firstOrFail();
        $this->actingAs($staff)->get('/my')->assertRedirect('/login');
    }
}
