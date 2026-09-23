<?php

namespace Tests\Feature;

use App\Models\CreditAssessment;
use App\Models\Enums\AssessmentStatus;
use App\Models\Enums\VerificationStatus;
use App\Models\KycProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The core process end to end:
 * KYC → Address Verification → Income Assessment → Risk Assessment → Purchase Limit.
 * Runs on the shared AtomShop DB inside a transaction; uploads go to a fake disk.
 */
class KycAssessmentFlowTest extends TestCase
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

    private function application(array $overrides = []): array
    {
        return [
            'full_name' => 'Test Customer',
            'cnic' => '42101-1234567-1',
            'mobile' => '0300 1234567',
            'date_of_birth' => '1990-05-01',
            'residential_address' => 'House 1, Street 2, Karachi',
            'cnic_front' => UploadedFile::fake()->image('front.jpg'),
            'cnic_back' => UploadedFile::fake()->image('back.jpg'),
            'selfie' => UploadedFile::fake()->image('selfie.jpg'),
            'employment_status' => 'salaried',
            'employer_name' => 'Acme Ltd',
            'income_source' => 'salary',
            'monthly_income' => 200000,
            'existing_instalments' => 10000,
            'monthly_expenses' => 90000,
            ...$overrides,
        ];
    }

    public function test_customer_submits_kyc_and_financial_profile(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.application.store'), $this->application())
            ->assertRedirect(route('account.dashboard'))
            ->assertSessionHasNoErrors();

        $profile = KycProfile::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('4210112345671', $profile->cnic);           // dashes stripped
        $this->assertSame('42101-1234567-1', $profile->cnic_formatted);
        $this->assertSame('03001234567', $profile->mobile);
        $this->assertTrue($profile->hasDocuments());
        $this->assertSame(VerificationStatus::Pending, $profile->verification_status);
        Storage::disk(config('atompay.kyc.disk'))->assertExists($profile->cnic_front_path);

        $a = CreditAssessment::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(100000, $a->disposable_income);           // 200k - 10k - 90k
        $this->assertSame(60000, $a->approved_limit);               // 30%
        $this->assertSame(20000, $a->max_instalment);               // min(10%, disposable)
        $this->assertSame(AssessmentStatus::Pending, $a->status);
        $this->assertNotNull($a->risk_score);

        // Provisional limit is not spendable until staff decide.
        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()->assertSee('Address verification pending')->assertSee('Not set yet');
    }

    public function test_low_disposable_income_caps_the_instalment(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();

        $this->actingAs($user)->post(route('account.application.store'), $this->application([
            'monthly_income' => 100000, 'existing_instalments' => 50000, 'monthly_expenses' => 45000,
        ]))->assertSessionHasNoErrors();

        $a = CreditAssessment::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(5000, $a->disposable_income);
        $this->assertSame(5000, $a->max_instalment);                // capped below 10%
        $this->assertSame('medium', $a->risk_category->value);      // affordability + no history + unverified
    }

    public function test_validation_rejects_minors_bad_cnic_and_missing_documents(): void
    {
        $this->actingAs($this->customer())->post(route('account.application.store'), $this->application([
            'date_of_birth' => now()->subYears(17)->format('Y-m-d'),
            'cnic' => '123',
            'selfie' => null,
        ]))->assertSessionHasErrors(['date_of_birth', 'cnic', 'selfie']);
    }

    public function test_staff_verify_address_and_decide_limit(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $user = $this->customer();
        $staff = $this->staff();

        $this->actingAs($user)->post(route('account.application.store'), $this->application());
        $assessment = CreditAssessment::where('user_id', $user->id)->firstOrFail();

        // A customer cannot reach the staff area.
        $this->actingAs($user)->get(route('staff.assessments.index'))->assertForbidden();

        $this->actingAs($staff)->get(route('staff.assessments.index'))
            ->assertOk()->assertSee($user->name);
        $this->actingAs($staff)->get(route('staff.assessments.show', $assessment))
            ->assertOk()->assertSee('Risk assessment')->assertSee('42101-1234567-1');

        // Section 2
        $this->actingAs($staff)->post(route('staff.assessments.address', $assessment), [
            'address_verified' => 1,
            'face_verified' => 1,
            'verified_at' => today()->format('Y-m-d'),
            'verification_status' => 'verified',
            'verification_notes' => 'Met at residence.',
            'verification_form' => UploadedFile::fake()->create('form.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $profile = $user->fresh()->kycProfile;
        $this->assertTrue($profile->isVerified());
        $this->assertSame($staff->id, $profile->verified_by);
        Storage::disk(config('atompay.kyc.disk'))->assertExists($profile->verification_form_path);

        // Sections 4-5
        $this->actingAs($staff)->post(route('staff.assessments.decide', $assessment), [
            'credit_history' => 'good',
            'approved_limit' => 50000,
            'max_instalment' => 15000,
            'approved_tenure' => 6,
            'status' => 'approved',
            'notes' => 'Approved at reduced limit.',
        ])->assertRedirect(route('staff.assessments.index'))->assertSessionHasNoErrors();

        $assessment->refresh();
        $this->assertSame(AssessmentStatus::Approved, $assessment->status);
        $this->assertSame(50000, $assessment->approved_limit);
        $this->assertSame($staff->id, $assessment->decided_by);
        $this->assertSame('good', $assessment->credit_history->value);
        $this->assertGreaterThanOrEqual(70, $assessment->risk_score); // KYC verified + good history

        // The customer now sees the approved limit and can spend it.
        $this->actingAs($user)->get(route('account.dashboard'))
            ->assertOk()->assertSee('PKR 50,000')->assertSee('6 months')->assertSee('Approved at reduced limit.');
    }

    public function test_documents_are_private_to_owner_and_staff(): void
    {
        Storage::fake(config('atompay.kyc.disk'));
        $owner = $this->customer();
        $this->actingAs($owner)->post(route('account.application.store'), $this->application());
        $profile = $owner->fresh()->kycProfile;

        $other = User::customers()->active()->where('id', '!=', $owner->id)->firstOrFail();

        $url = route('documents.show', [$profile, 'selfie']);

        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($this->staff())->get($url)->assertOk();
        $this->actingAs($owner)->get($url)->assertOk();
    }
}
