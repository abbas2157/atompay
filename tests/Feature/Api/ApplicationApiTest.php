<?php

namespace Tests\Feature\Api;

use App\Models\CreditAssessment;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\VerificationStatus;

class ApplicationApiTest extends ApiTestCase
{
    private function income(array $overrides = []): array
    {
        return [
            'employment_status' => 'salaried',
            'employer_name' => 'Acme Ltd',
            'income_source' => 'salary',
            'monthly_income' => 200000,
            'existing_instalments' => 10000,
            'monthly_expenses' => 90000,
            ...$overrides,
        ];
    }

    public function test_options_list_the_form_pickers(): void
    {
        $this->getJson('/api/v1/options')
            ->assertOk()
            ->assertJsonFragment(['value' => 'salaried', 'label' => 'Salaried', 'has_employer' => true])
            ->assertJsonFragment(['value' => 'retired', 'label' => 'Retired', 'has_employer' => false])
            ->assertJsonFragment(['value' => 'remittance', 'label' => 'Remittance']);
    }

    public function test_applying_needs_a_profile_first(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/application')
            ->assertOk()
            ->assertJsonPath('data.can_apply', false)
            ->assertJsonPath('data.requires', 'profile')
            ->assertJsonPath('data.latest', null);

        $this->freshRequest()->withToken($token)->postJson('/api/v1/application', $this->income())
            ->assertStatus(409)
            ->assertJsonPath('code', 'profile_required');

        $this->assertSame(0, CreditAssessment::where('user_id', $user->id)->count());
    }

    public function test_a_customer_applies_and_the_provisional_limit_stays_hidden(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);

        $this->withToken($this->tokenFor($user))->postJson('/api/v1/application', $this->income())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_usable', false)
            ->assertJsonPath('data.disposable_income', 100000)       // 200k - 10k - 90k
            ->assertJsonPath('data.employment_status_label', 'Salaried')
            ->assertJsonPath('data.approved_limit', null)            // computed, but not shown until decided
            ->assertJsonMissingPath('data.risk_score');

        $a = CreditAssessment::where('user_id', $user->id)->sole();
        $this->assertSame(60000, $a->approved_limit);               // server still computed it
        $this->assertSame(AssessmentStatus::Pending, $a->status);
    }

    public function test_income_rules_match_the_website(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/v1/application', $this->income(['employer_name' => '', 'monthly_income' => 500, 'income_source' => 'lottery']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['employer_name', 'monthly_income', 'income_source'])
            ->assertJsonPath('errors.employer_name.0', 'Please tell us your employer or business name.');
    }

    public function test_the_dashboard_shows_the_limit_once_decided(): void
    {
        $user = $this->makeCustomer();
        $token = $this->tokenFor($user);

        $this->withToken($token)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kyc_status', 'not_started')
            ->assertJsonPath('data.banner.action', 'apply')
            ->assertJsonPath('data.limit.has_limit', false)
            ->assertJsonPath('data.stages.0.key', 'kyc')
            ->assertJsonPath('data.stages.0.state', 'current')
            ->assertJsonPath('data.next_due', null);

        $this->makeProfile($user, VerificationStatus::Verified);
        $this->makeDecidedAssessment($user, 'conditional', 45000);

        $this->freshRequest()->withToken($token)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.application_status', 'conditional')
            ->assertJsonPath('data.banner.tone', 'done')
            ->assertJsonPath('data.banner.title', 'Limit approved with conditions')
            ->assertJsonPath('data.limit.has_limit', true)
            ->assertJsonPath('data.limit.status', 'conditional')
            ->assertJsonPath('data.limit.approved', 45000)
            ->assertJsonPath('data.limit.available', 45000)
            ->assertJsonPath('data.limit.tenure', 12);
    }

    public function test_a_profile_without_income_is_asked_for_income(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.kyc_status', 'pending')
            ->assertJsonPath('data.banner.title', 'Tell us about your income')
            ->assertJsonPath('data.banner.action', 'application');
    }

    public function test_reapplying_keeps_the_limit_in_force_and_history_lists_both(): void
    {
        $user = $this->makeCustomer();
        $this->makeProfile($user, VerificationStatus::Verified);
        $approved = $this->makeDecidedAssessment($user);
        $token = $this->tokenFor($user);

        $this->withToken($token)->postJson('/api/v1/application', $this->income(['monthly_income' => 400000]))->assertCreated();

        $this->freshRequest()->withToken($token)->getJson('/api/v1/application')
            ->assertJsonPath('data.latest.status', 'pending')
            ->assertJsonPath('data.active.id', $approved->id)
            ->assertJsonPath('data.active.approved_limit', 60000);

        $this->freshRequest()->withToken($token)->getJson('/api/v1/application/history')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', $approved->id);
    }

    public function test_income_estimate_is_computed_by_the_server(): void
    {
        $this->postJson('/api/v1/estimate', ['monthly_income' => 150000])
            ->assertOk()
            ->assertExactJson(['data' => ['monthly_income' => 150000, 'estimated_limit' => 45000, 'estimated_max_instalment' => 15000]]);

        $this->postJson('/api/v1/estimate', ['monthly_income' => 10])->assertUnprocessable();
    }
}
