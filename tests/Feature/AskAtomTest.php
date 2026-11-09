<?php

namespace Tests\Feature;

use App\Services\AskAtomService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Ask Atom. Answers come from built-in rules - no model, no key, nothing a
 * visitor types leaving the server - so these tests pin the routing from a
 * question to the right answer, and that every figure in it comes from the
 * same services the pages use.
 */
class AskAtomTest extends TestCase
{
    use DatabaseTransactions;

    private function ask(string $question): array
    {
        return app(AskAtomService::class)->answer($question);
    }

    public function test_it_answers_what_atompay_costs_with_the_real_plan(): void
    {
        $answer = $this->ask('how much extra does atompay cost?');

        $this->assertStringContainsString('0% markup', $answer['answer']);
        $this->assertStringContainsString('no advance', $answer['answer']);
        $this->assertStringContainsString('Rs. 33,333', $answer['answer']);   // 100,000 / 3
        $this->assertStringContainsString('Rs. 100,000', $answer['answer']);
    }

    /** A price in the question is priced with the real plan, not a canned reply. */
    public function test_it_prices_a_plan_for_a_named_amount(): void
    {
        $answer = $this->ask('plan for 60,000');

        $this->assertStringContainsString('Rs. 60,000', $answer['answer']);
        $this->assertStringContainsString('Rs. 20,000', $answer['answer']);   // 60,000 / 3
        $this->assertStringContainsString('3 months', $answer['answer']);
    }

    public function test_an_uneven_price_explains_the_rounding_month(): void
    {
        $answer = $this->ask('plan for 100000');

        $this->assertStringContainsString('Rs. 33,334', $answer['answer']);   // the last month
        $this->assertStringContainsString('last month', $answer['answer']);
    }

    public function test_it_explains_the_limit_from_config(): void
    {
        $answer = $this->ask('how much can i spend?');

        $limit = (int) (config('atompay.credit.limit_ratio') * 100);
        $cap = (int) (config('atompay.credit.instalment_ratio') * 100);

        $this->assertStringContainsString($limit.'% of the monthly income', $answer['answer']);
        $this->assertStringContainsString($cap.'%', $answer['answer']);
    }

    public function test_it_refuses_to_promise_a_longer_term(): void
    {
        $answer = $this->ask('can i pay over 12 months?');

        $this->assertStringContainsString('Always three months', $answer['answer']);
        $this->assertStringContainsString('seller financing', $answer['answer']);
    }

    public function test_it_counts_the_eligible_catalogue(): void
    {
        $count = app(\App\Services\CatalogueService::class)->count();
        $answer = $this->ask('which products can i buy?');

        if ($count > 0) {
            $this->assertStringContainsString(number_format($count), $answer['answer']);
        }

        $this->assertSame(route('products'), $answer['actions'][0]['url']);
    }

    public function test_it_links_both_app_stores(): void
    {
        $answer = $this->ask('do you have an android app?');
        $labels = array_column($answer['actions'], 'label');

        $this->assertContains('Google Play', $labels);
        $this->assertContains('App Store', $labels);
        $this->assertStringContainsString('Google Play and the App Store', $answer['answer']);
    }

    /** Anything the shop owns is handed to the shop rather than guessed at. */
    public function test_it_hands_delivery_and_warranty_to_atomshop(): void
    {
        $answer = $this->ask('when will my order be delivered?');

        $this->assertStringContainsString('belongs to Atomshop', $answer['answer']);
        $this->assertSame(config('atompay.shop_url'), $answer['actions'][0]['url']);
    }

    public function test_an_unknown_question_says_so_and_offers_the_faqs(): void
    {
        $answer = $this->ask('what is the weather in Lahore');

        $this->assertStringContainsString('not sure about that one', $answer['answer']);
        $this->assertSame(route('faq'), $answer['actions'][0]['url']);
    }

    /* --------------------------------------------------------- the endpoint */

    public function test_the_endpoint_answers_as_json(): void
    {
        $this->postJson(route('ask-atom'), ['question' => 'what does atompay cost?'])
            ->assertOk()
            ->assertJsonStructure(['answer', 'actions', 'chips']);
    }

    public function test_the_endpoint_validates_the_question(): void
    {
        $this->postJson(route('ask-atom'), ['question' => ''])->assertUnprocessable();
        $this->postJson(route('ask-atom'), ['question' => 'a'])->assertUnprocessable();
        $this->postJson(route('ask-atom'), ['question' => str_repeat('a', 301)])->assertUnprocessable();
    }

    /**
     * A question is never echoed into the answer, so there is nothing to inject
     * through. The panel also sets a visitor's own words as text, not HTML.
     */
    public function test_a_question_is_never_echoed_into_the_answer(): void
    {
        $answer = $this->ask('<script>alert(1)</script> what does it cost');

        $this->assertStringNotContainsString('<script>', $answer['answer']);
        $this->assertStringNotContainsString('alert(1)', $answer['answer']);
    }

    public function test_the_panel_is_on_the_page_in_v2_only(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);
        $this->get('/')
            ->assertOk()
            ->assertSee('atom-fab', false)
            ->assertSee('id="atom-config"', false)
            ->assertSee(route('ask-atom'), false);

        config(['design.default' => 'v1', 'design.active' => 'v1']);
        $this->get('/')->assertOk()->assertDontSee('atom-fab', false);
    }

    /** The staff review screens are internal; Atom has no business there. */
    public function test_the_panel_is_not_on_the_staff_screens(): void
    {
        config(['design.default' => 'v2', 'design.active' => 'v2']);

        $staff = \App\Models\User::whereIn('role', config('atompay.staff_roles'))
            ->where('status', 'active')->firstOrFail();

        $this->actingAs($staff)->get(route('staff.assessments.index'))
            ->assertOk()
            ->assertDontSee('atom-fab', false);
    }
}
